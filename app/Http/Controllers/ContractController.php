<?php

namespace App\Http\Controllers;

use App\Models\ContractSignature;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\OtpCode;
use App\Services\ContractService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function generate(Lease $lease, ContractService $service)
    {
        $service->generate($lease);

        return redirect()->route('admin.leases.contract.edit', $lease)->with('success', 'Contrato-base carregado. Faça os ajustes finais antes de finalizar.');
    }

    public function editFinal(Lease $lease, ContractService $service)
    {
        $contract = $service->generate($lease);
        abort_unless($contract->status === 'in_production', 422, 'Este contrato não está mais disponível para edição.');
        $contract->load('template');

        return view('admin.leases.contract', compact('lease', 'contract'));
    }

    public function updateFinal(Request $request, Lease $lease, ContractService $service)
    {
        $data = $request->validate(['final_content' => ['required', 'string']]);
        $contract = $lease->contract()->firstOrFail();
        $service->saveDraft($contract, $data['final_content']);

        return back()->with('success', 'Ajustes do contrato salvos.');
    }

    public function finalize(Request $request, Lease $lease, ContractService $service)
    {
        $data = $request->validate(['final_content' => ['required', 'string']]);
        $contract = $lease->contract()->firstOrFail();
        $service->saveDraft($contract, $data['final_content']);
        $contract->update(['status' => 'finalized']);

        return redirect()->route('admin.leases.show', $lease)->with('success', 'Contrato finalizado e bloqueado para alterações. Agora ele pode ser enviado para assinatura.');
    }

    public function requestSignatures(Lease $lease)
    {
        $contract = $lease->contract()->firstOrFail();
        abort_unless($contract->status === 'finalized', 422, 'Finalize o contrato antes de solicitar as assinaturas.');
        $contract->update(['status' => 'awaiting_signatures']);
        $lease->update(['status' => 'awaiting_signatures']);
        $lease->property()->update(['status' => 'paused']);

        return redirect()->route('contracts.show', $contract)->with('success', 'Contrato enviado ao locatário. Após a assinatura com foto, o administrador poderá assinar e concluir o processo.');
    }

    public function show(Request $request, LeaseContract $contract)
    {
        $this->authorizeAccess($request, $contract);
        $contract->load('template', 'lease.client', 'lease.property.group', 'signatures');

        return view('contracts.show', compact('contract'));
    }

    public function sendOtp(Request $request, LeaseContract $contract, WhatsAppService $whatsApp)
    {
        $this->authorizeAccess($request, $contract);
        abort_unless($contract->status === 'awaiting_signatures', 422, 'O contrato ainda não está aguardando assinaturas.');

        $type = $this->signerType($request);
        $this->assertSignerCanProceed($contract, $type);
        abort_if($contract->signatures()->where('signer_type', $type)->exists(), 422, 'Este participante já assinou.');

        $contract->load('lease.client', 'lease.property.group');
        $phone = $type === 'client'
            ? $contract->lease->client->phone
            : ($request->user()->phone ?: $contract->lease->property->group->phone);
        abort_if(blank($phone), 422, 'Não há telefone cadastrado para o envio do código.');

        $code = (string) random_int(100000, 999999);
        $otp = OtpCode::create([
            'contract_id' => $contract->id,
            'signer_type' => $type,
            'phone' => $phone,
            'code_hash' => $this->hashCode($code),
            'expires_at' => now()->addMinutes(config('business.otp_expiration_minutes')),
        ]);
        $delivery = $whatsApp->send(
            $phone,
            "AlugaPro: seu código de assinatura é {$code}. Ele expira em ".config('business.otp_expiration_minutes').' minutos.',
            'signature_otp',
            $type,
            $contract->lease,
        );

        if (app()->isLocal()) {
            session()->flash('dev_otp', $code);
        } elseif ($delivery->status !== 'sent') {
            $otp->delete();

            return back()->withErrors([
                'whatsapp' => 'Não foi possível enviar o código pelo WhatsApp. Verifique a conexão WPPConnect e tente novamente.',
            ]);
        }

        return back()->with(
            'success',
            $delivery->status === 'sent'
                ? 'Código de assinatura enviado por WhatsApp.'
                : 'Código simulado para desenvolvimento.',
        );
    }

    public function sign(Request $request, LeaseContract $contract)
    {
        $this->authorizeAccess($request, $contract);
        abort_unless($contract->status === 'awaiting_signatures', 422, 'O contrato ainda não está aguardando assinaturas.');

        $type = $this->signerType($request);
        $this->assertSignerCanProceed($contract, $type);
        abort_if($contract->signatures()->where('signer_type', $type)->exists(), 422, 'Este participante já assinou.');

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'accepted' => ['accepted'],
            'photo' => [
                Rule::requiredIf($type === 'client'),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $otp = OtpCode::where('contract_id', $contract->id)
            ->where('signer_type', $type)
            ->whereNull('used_at')
            ->latest()
            ->first();
        abort_if(! $otp || $otp->expires_at->isPast(), 422, 'Código expirado. Solicite um novo.');

        $otp->increment('attempts');
        $otp->refresh();
        abort_if($otp->attempts > 5, 429, 'Muitas tentativas. Solicite um novo código.');
        abort_unless(hash_equals($otp->code_hash, $this->hashCode($data['code'])), 422, 'Código inválido.');

        $photoBase64 = null;
        $photoMimeType = null;
        $photoSha256 = null;
        if ($type === 'client') {
            $photo = $request->file('photo');
            $photoContents = file_get_contents($photo->getRealPath());
            abort_if($photoContents === false, 422, 'Não foi possível processar a foto do locatário.');
            $photoMimeType = $photo->getMimeType();
            $photoSha256 = hash('sha256', $photoContents);
            $photoBase64 = 'data:'.$photoMimeType.';base64,'.base64_encode($photoContents);
        }

        $contract->load('lease.client', 'lease.property.group');
        $name = $type === 'client' ? $contract->lease->client->name : $request->user()->name;
        $document = $type === 'client' ? $contract->lease->client->cpf : $request->user()->cpf;

        DB::transaction(function () use (
            $request,
            $contract,
            $otp,
            $type,
            $name,
            $document,
            $photoBase64,
            $photoMimeType,
            $photoSha256,
        ) {
            $lockedContract = LeaseContract::query()->lockForUpdate()->findOrFail($contract->id);
            $this->assertSignerCanProceed($lockedContract, $type);
            abort_if($lockedContract->signatures()->where('signer_type', $type)->exists(), 422, 'Este participante já assinou.');

            $lockedOtp = OtpCode::query()->lockForUpdate()->findOrFail($otp->id);
            abort_if($lockedOtp->used_at, 422, 'Este código já foi utilizado.');

            $signedAt = now();
            $evidenceHash = hash('sha256', implode('|', [
                $lockedContract->content_hash,
                $type,
                $lockedOtp->id,
                $signedAt->toIso8601String(),
                $photoSha256 ?? '',
            ]));

            $lockedOtp->update(['used_at' => $signedAt]);
            ContractSignature::create([
                'contract_id' => $lockedContract->id,
                'signer_type' => $type,
                'signer_name' => $name,
                'signer_document' => $document,
                'verification_channel' => 'whatsapp_otp',
                'photo_base64' => $photoBase64,
                'photo_mime_type' => $photoMimeType,
                'photo_sha256' => $photoSha256,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'evidence_hash' => $evidenceHash,
                'signed_at' => $signedAt,
            ]);

            $signatureData = [
                'name' => $name,
                'document' => $document,
                'signed_at' => $signedAt->toIso8601String(),
                'channel' => 'whatsapp_otp',
                'evidence_hash' => $evidenceHash,
            ];
            if ($photoSha256) {
                $signatureData['photo_sha256'] = $photoSha256;
            }

            $lockedContract->update([
                $type === 'client' ? 'tenant_signature' : 'landlord_signature' => $signatureData,
            ]);

            $hasTenantSignature = $lockedContract->signatures()->where('signer_type', 'client')->exists();
            $hasAdminSignature = $lockedContract->signatures()->where('signer_type', 'responsible')->exists();
            if ($hasTenantSignature && $hasAdminSignature) {
                $lockedContract->update(['status' => 'signed', 'signed_at' => now()]);
                $lockedContract->lease()->update(['status' => 'active']);
                $lockedContract->lease()->firstOrFail()->property()->update(['status' => 'rented']);
            }
        });

        return back()->with(
            'success',
            $type === 'client'
                ? 'Assinatura e foto do locatário registradas. Agora o administrador poderá assinar.'
                : 'Assinatura do administrador registrada. Contrato concluído.',
        );
    }

    private function signerType(Request $request): string
    {
        return $request->user()->role === 'client' ? 'client' : 'responsible';
    }

    private function assertSignerCanProceed(LeaseContract $contract, string $type): void
    {
        if ($type === 'responsible') {
            abort_unless(
                $contract->signatures()->where('signer_type', 'client')->exists(),
                422,
                'O locatário precisa assinar o contrato e enviar a foto antes do administrador.',
            );
        }
    }

    private function authorizeAccess(Request $request, LeaseContract $contract): void
    {
        if($request->user()->role==='client'){
            abort_unless(in_array($contract->status,['awaiting_signatures','signed'],true),403);
            abort_unless($contract->lease()->where('client_id',$request->user()->client?->id)->exists(),403);
        }
        else abort_unless($request->user()->isAdmin(),403);
    }

    private function hashCode(string $code): string{return hash('sha256',$code.'|'.config('app.key'));}
}

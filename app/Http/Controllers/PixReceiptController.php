<?php

namespace App\Http\Controllers;

use App\Models\PixPayment;
use App\Services\ChargePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PixReceiptController extends Controller
{
    public function store(Request $request, PixPayment $pixPayment, ChargePaymentService $payments)
    {
        $this->ensureClientOwnsPayment($request, $pixPayment);
        $data = $request->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ], [
            'receipt.required' => 'Selecione o comprovante do pagamento.',
            'receipt.mimes' => 'O comprovante deve ser uma imagem JPG, PNG ou um arquivo PDF.',
            'receipt.max' => 'O comprovante pode ter no máximo 8 MB.',
            'receipt.uploaded' => 'Não foi possível enviar o comprovante. Tente novamente com um arquivo de até 8 MB.',
        ]);

        $file = $data['receipt'];
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'receipt' => 'Não foi possível ler o comprovante enviado.',
            ]);
        }

        DB::transaction(function () use ($request, $pixPayment, $payments, $file, $contents): void {
            $charge = $pixPayment->charge;
            $pixPayment->update([
                'receipt_uploaded_by_user_id' => $request->user()->id,
                'receipt_original_name' => $this->safeFileName($file->getClientOriginalName()),
                'receipt_mime_type' => Str::limit($file->getMimeType() ?: 'application/octet-stream', 100, ''),
                'receipt_size_bytes' => strlen($contents),
                'receipt_checksum_sha256' => hash('sha256', $contents),
                'receipt_document_base64' => base64_encode($contents),
                'receipt_uploaded_at' => now(),
            ]);

            $result = $payments->settle($charge, 'pix_receipt');
            $paidAt = $result['charge']->paid_at ?: now();
            $pixPayment->update(['status' => 'paid', 'paid_at' => $paidAt]);
        });

        return redirect()->route('client.charge', $pixPayment->charge_id)
            ->with('payment_id', $pixPayment->id)
            ->with('success', 'Comprovante enviado e pagamento baixado com sucesso.');
    }

    public function clientShow(Request $request, PixPayment $pixPayment)
    {
        $this->ensureClientOwnsPayment($request, $pixPayment);

        return $this->receiptResponse($pixPayment);
    }

    public function adminShow(PixPayment $pixPayment)
    {
        $pixPayment->load('charge');
        abort_unless($pixPayment->charge, 404);

        return $this->receiptResponse($pixPayment);
    }

    private function ensureClientOwnsPayment(Request $request, PixPayment $pixPayment): void
    {
        $pixPayment->load('charge');
        abort_unless($pixPayment->charge?->client_id === $request->user()->client?->id, 404);
    }

    private function receiptResponse(PixPayment $pixPayment)
    {
        abort_unless($pixPayment->receipt_original_name && $pixPayment->receipt_document_base64, 404);
        $contents = base64_decode($pixPayment->receipt_document_base64, true);
        abort_if(
            $contents === false || ! hash_equals((string) $pixPayment->receipt_checksum_sha256, hash('sha256', $contents)),
            500,
            'O comprovante armazenado está corrompido.'
        );

        $fileName = $this->safeFileName($pixPayment->receipt_original_name);
        $fallbackName = preg_replace('/[^A-Za-z0-9._ -]/', '', Str::ascii($fileName)) ?: 'comprovante';
        $mimeType = in_array($pixPayment->receipt_mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
            ? $pixPayment->receipt_mime_type
            : 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $fileName, $fallbackName),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';

        return Str::limit(trim($name) ?: 'comprovante', 240, '');
    }
}

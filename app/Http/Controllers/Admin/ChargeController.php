<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\PropertyGroup;
use App\Models\SolarReading;
use App\Models\WhatsAppAutomation;
use App\Services\BillingService;
use App\Services\ChargePaymentService;
use App\Services\PixService;
use App\Services\WhatsAppService;
use App\Support\AdminGroupContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChargeController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth() : now()->startOfMonth();
        $groupId = AdminGroupContext::groupId($request->user()) ?? ($request->integer('group') ?: null);
        $query = Charge::with('lease.property.group', 'client', 'solarReading')->whereDate('reference_month', $month)->when($groupId, fn ($q) => $q->whereHas('lease.property', fn ($p) => $p->where('group_id', $groupId)));
        $charges = (clone $query)->orderBy('due_date')->get()->groupBy(fn ($charge) => $charge->due_date->day);
        $summary = [
            'total' => (clone $query)->sum('amount'),
            'total_count' => (clone $query)->count(),
            'received' => (clone $query)->where('status', 'paid')->sum('amount'),
            'received_count' => (clone $query)->where('status', 'paid')->count(),
            'open' => (clone $query)->where('status', 'open')->sum('amount'),
            'open_count' => (clone $query)->where('status', 'open')->count(),
        ];
        $groups = PropertyGroup::orderBy('name')->get();

        return view('admin.charges.index', compact('charges', 'summary', 'groups', 'month', 'groupId'));
    }

    public function solarReceipt(Charge $charge)
    {
        return view('admin.charges.solar-receipt', $this->solarReceiptData($charge));
    }

    public function sendSolarReceipt(Charge $charge, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $this->solarReceiptData($charge);
        $reading = $data['reading'];
        $contents = base64_decode((string) $reading->photo_base64, true);

        if ($contents === false || $contents === '') {
            return back()->withErrors(['whatsapp' => 'Esta medição não possui a foto do medidor para enviar.']);
        }

        if (! in_array($reading->photo_mime_type, ['image/jpeg', 'image/png'], true)) {
            return back()->withErrors(['whatsapp' => 'A foto desta medição precisa estar em JPEG ou PNG para ser enviada pelo WhatsApp.']);
        }

        $reference = $reading->reference_month->format('Y-m');
        $extension = $reading->photo_mime_type === 'image/png' ? 'png' : 'jpg';
        $log = $whatsApp->sendImage(
            $charge->client->phone,
            $contents,
            "extrato-energia-solar-{$reference}.{$extension}",
            $data['message'],
            'solar_receipt',
            'client',
            $charge,
            $reading->photo_mime_type,
        );

        if ($log->status === 'sent') {
            return back()->with('success', 'Extrato de energia solar enviado por WhatsApp.');
        }

        $message = $log->status === 'simulated'
            ? 'A tentativa foi registrada, mas o WhatsApp ainda não está configurado e conectado.'
            : ($log->error ?: 'O WhatsApp não confirmou o envio do extrato.');

        return back()->withErrors(['whatsapp' => $message]);
    }

    public function generate(Request $request, BillingService $billing)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $billing->generateMonth(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth());

        return back()->with('success', "{$count} cobrança(s) criada(s).");
    }

    public function storeOneOff(Request $request, Lease $lease, ChargePaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['rent', 'solar'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['open', 'paid'])],
        ], [
            'type.in' => 'Selecione aluguel ou energia solar.',
            'amount.decimal' => 'Informe o valor com no máximo duas casas decimais.',
            'amount.min' => 'O valor da cobrança deve ser maior que zero.',
            'due_date.date_format' => 'Informe uma data de vencimento válida.',
            'status.in' => 'Selecione um status válido para a cobrança.',
        ]);

        $paid = $data['status'] === 'paid';
        $typeLabel = $data['type'] === 'solar' ? 'energia solar' : 'aluguel';

        $charge = $lease->charges()->create([
            'client_id' => $lease->client_id,
            'type' => $data['type'],
            'generation_key' => null,
            'reference_month' => Carbon::createFromFormat('Y-m-d', $data['due_date'])->startOfMonth(),
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'status' => 'open',
            'description' => 'Cobrança avulsa de '.$typeLabel,
            'paid_at' => null,
            'payment_method' => null,
        ]);

        if ($paid) {
            $payments->settle($charge, 'manual');
        }

        return redirect()
            ->to(route('admin.leases.show', $lease).'#cobrancas')
            ->with('success', 'Cobrança avulsa criada.');
    }

    public function paid(Charge $charge, ChargePaymentService $payments)
    {
        $payments->settle($charge, 'manual');

        return back()->with('success', 'Pagamento registrado.');
    }

    public function updateAmount(Request $request, Charge $charge, ChargePaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ], [
            'amount.min' => 'Para zerar a cobrança, use a ação “Zerar e dar baixa”.',
            'amount.decimal' => 'Informe o valor com no máximo duas casas decimais.',
        ]);

        $result = $payments->adjustAmount($charge, (float) $data['amount'], $request->user()->getKey());

        return back()->with('success', $result['changed'] ? 'Valor da cobrança atualizado.' : 'O valor da cobrança não foi alterado.');
    }

    public function waive(Request $request, Charge $charge, ChargePaymentService $payments)
    {
        $payments->waive($charge, $request->user()->getKey());

        return back()->with('success', 'Cobrança zerada e baixada sem recebimento.');
    }

    public function reopen(Charge $charge, ChargePaymentService $payments)
    {
        $payments->reopen($charge);

        return back()->with('success', 'Cobrança reaberta.');
    }

    public function sendOverdueNotice(Charge $charge, WhatsAppService $whatsApp): RedirectResponse
    {
        $today = now(config('business.billing_timezone', 'America/Sao_Paulo'))->toDateString();
        abort_unless(
            $charge->status === 'open' && $charge->due_date->toDateString() < $today,
            422,
            'A cobrança precisa estar vencida e em aberto para enviar uma mensagem de atraso.',
        );

        $charge->load('client', 'lease.property.group');
        $automation = WhatsAppAutomation::for(WhatsAppAutomation::OVERDUE);
        $log = $whatsApp->send(
            $charge->client->phone,
            $automation->render($charge),
            WhatsAppAutomation::OVERDUE,
            'client',
            $charge,
            $automation->templateParameters($charge),
        );

        if ($log->status === 'sent') {
            return back()->with('success', 'Cobrança de atraso enviada por WhatsApp.');
        }

        $message = $log->status === 'simulated'
            ? 'A tentativa foi registrada, mas o WhatsApp ainda não está configurado e conectado.'
            : ($log->error ?: 'O WhatsApp não confirmou o envio da cobrança de atraso.');

        return back()->withErrors(['whatsapp' => $message]);
    }

    public function pix(Charge $charge, PixService $pix)
    {
        $payment = $pix->createFor($charge);

        return redirect()
            ->to(route('admin.leases.show', $charge->lease_id).'#pix-gerado')
            ->with('pix_payment_id', $payment->id)
            ->with('success', 'Pix estático gerado. Copie o código para compartilhar com o cliente.');
    }

    /** @return array{charge: Charge, reading: SolarReading, previousReading: ?SolarReading, message: string} */
    private function solarReceiptData(Charge $charge): array
    {
        abort_unless($charge->type === 'solar', 404);

        $charge->load(['client', 'lease.property.group', 'solarReading.solarConfig']);
        abort_unless($charge->solarReading, 404, 'Esta cobrança ainda não possui uma leitura solar vinculada.');

        $reading = $charge->solarReading;
        $previousReading = SolarReading::query()
            ->where('solar_config_id', $reading->solar_config_id)
            ->where('reference_month', '<', $reading->reference_month)
            ->latest('reference_month')
            ->first();

        return [
            'charge' => $charge,
            'reading' => $reading,
            'previousReading' => $previousReading,
            'message' => $this->solarReceiptMessage($charge, $reading, $previousReading),
        ];
    }

    private function solarReceiptMessage(Charge $charge, SolarReading $reading, ?SolarReading $previousReading): string
    {
        $previousDate = $previousReading?->created_at?->timezone(config('business.billing_timezone', 'America/Sao_Paulo'))->format('d/m/Y') ?? 'leitura inicial';
        $currentDate = $reading->created_at?->timezone(config('business.billing_timezone', 'America/Sao_Paulo'))->format('d/m/Y') ?? 'não informada';
        $price = (float) $reading->amount / max((float) $reading->consumption_kwh, 1);
        $price = $reading->solarConfig?->price_per_kwh !== null ? (float) $reading->solarConfig->price_per_kwh : $price;

        return implode("\n", [
            '☀️ *Extrato de energia solar*',
            "Cliente: {$charge->client->name}",
            "Imóvel: {$charge->lease->property->title}",
            "Referência: {$reading->reference_month->translatedFormat('F/Y')}",
            "Leitura anterior: ".number_format((float) $reading->previous_reading, 3, ',', '.')." kWh ({$previousDate})",
            "Leitura atual: ".number_format((float) $reading->meter_reading, 3, ',', '.')." kWh ({$currentDate})",
            "Consumo: ".number_format((float) $reading->consumption_kwh, 3, ',', '.').' kWh',
            'Valor do kWh: R$ '.number_format($price, 4, ',', '.'),
            'Total: R$ '.number_format((float) $reading->amount, 2, ',', '.'),
            'Vencimento: '.$charge->due_date->format('d/m/Y'),
        ]);
    }
}

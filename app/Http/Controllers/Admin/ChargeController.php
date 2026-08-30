<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\PropertyGroup;
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
        $query = Charge::with('lease.property.group', 'client')->whereDate('reference_month', $month)->when($groupId, fn ($q) => $q->whereHas('lease.property', fn ($p) => $p->where('group_id', $groupId)));
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

    public function generate(Request $request, BillingService $billing)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $billing->generateMonth(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth());

        return back()->with('success', "{$count} cobrança(s) criada(s).");
    }

    public function storeOneOff(Request $request, Lease $lease): RedirectResponse
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

        $lease->charges()->create([
            'client_id' => $lease->client_id,
            'type' => $data['type'],
            'generation_key' => null,
            'reference_month' => Carbon::createFromFormat('Y-m-d', $data['due_date'])->startOfMonth(),
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'status' => $data['status'],
            'description' => 'Cobrança avulsa de '.$typeLabel,
            'paid_at' => $paid ? now() : null,
            'payment_method' => $paid ? 'manual' : null,
        ]);

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
}

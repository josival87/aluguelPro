<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PropertyGroup;
use App\Models\WhatsAppAutomation;
use App\Services\BillingService;
use App\Services\ChargePaymentService;
use App\Services\PixService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function index(Request $request)
    {
        $month=$request->filled('month')?Carbon::createFromFormat('Y-m',$request->month)->startOfMonth():now()->startOfMonth();
        $groupId=$request->integer('group')?:null;
        $query=Charge::with('lease.property.group','client')->whereDate('reference_month',$month)->when($groupId,fn($q)=>$q->whereHas('lease.property',fn($p)=>$p->where('group_id',$groupId)));
        $charges=(clone $query)->orderBy('due_date')->get()->groupBy(fn($charge)=>$charge->due_date->day);
        $summary=[
            'total'=>(clone $query)->sum('amount'),
            'total_count'=>(clone $query)->count(),
            'received'=>(clone $query)->where('status','paid')->sum('amount'),
            'received_count'=>(clone $query)->where('status','paid')->count(),
            'open'=>(clone $query)->where('status','open')->sum('amount'),
            'open_count'=>(clone $query)->where('status','open')->count(),
        ];
        $groups=PropertyGroup::orderBy('name')->get();
        return view('admin.charges.index',compact('charges','summary','groups','month','groupId'));
    }

    public function generate(Request $request,BillingService $billing)
    {
        $data=$request->validate(['month'=>['required','date_format:Y-m']]);$count=$billing->generateMonth(Carbon::createFromFormat('Y-m',$data['month'])->startOfMonth());
        return back()->with('success',"{$count} cobrança(s) criada(s).");
    }
    public function paid(Charge $charge, ChargePaymentService $payments)
    {
        $payments->settle($charge, 'manual');
        return back()->with('success','Pagamento registrado.');
    }
    public function reopen(Charge $charge, ChargePaymentService $payments)
    {
        $payments->reopen($charge);
        return back()->with('success','Cobrança reaberta.');
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

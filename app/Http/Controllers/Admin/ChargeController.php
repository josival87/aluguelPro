<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PropertyGroup;
use App\Services\BillingService;
use App\Services\ChargePaymentService;
use Carbon\Carbon;
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
}

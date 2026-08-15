<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Lease;
use App\Services\MoneyCalculator;
use App\Services\PixService;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $client=$request->user()->client;abort_unless($client,403);$client->load(['leases.property.photos','leases.contract.signatures','leases.charges'=>fn($q)=>$q->orderByDesc('due_date')]);
        return view('client.dashboard',compact('client'));
    }
    public function lease(Request $request,Lease $lease)
    {
        abort_unless($lease->client_id===$request->user()->client?->id,403);$lease->load('property.group.rules','contract.signatures','charges','solarConfig.readings.charge');return view('client.lease',compact('lease'));
    }
    public function charge(Request $request,Charge $charge,MoneyCalculator $calculator)
    {
        abort_unless($charge->client_id===$request->user()->client?->id,403);$charge->load('lease.property','pixPayments');$payable=$calculator->payable($charge);return view('client.charge',compact('charge','payable'));
    }
    public function pix(Request $request,Charge $charge,PixService $pix)
    {
        abort_unless($charge->client_id===$request->user()->client?->id,403);abort_unless($charge->status==='open',422,'Esta cobrança já foi paga.');
        $payment=$pix->createFor($charge);return redirect()->route('client.charge',$charge)->with('payment_id',$payment->id)->with('success','Pix gerado. O código expira em 30 minutos.');
    }
}

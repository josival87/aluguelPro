<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()->withCount('leases')->when($request->q, fn ($q, $term) => $q->where(fn ($sub) => $sub->where('name', 'ilike', "%{$term}%")->orWhere('cpf', 'like', "%{$term}%")))->latest()->paginate(15)->withQueryString();
        return view('admin.clients.index', compact('clients'));
    }
    public function create() { return view('admin.clients.form', ['client' => new Client]); }
    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $user = User::create(['name'=>$data['name'],'email'=>$data['email'],'login'=>$data['email'] ?? preg_replace('/\D/', '', $data['cpf']),'cpf'=>$data['cpf'],'phone'=>$data['phone'],'role'=>'client','password'=>$data['password']]);
            Client::create([...$data, 'user_id'=>$user->id, 'status'=>$data['status'] ?? 'active']);
        });
        return redirect()->route('admin.clients.index')->with('success', 'Cliente cadastrado.');
    }
    public function show(Client $client)
    {
        $client->load(['documents', 'leases.property', 'leases.charges' => fn ($q) => $q->latest('due_date')]);
        $summary = ['open' => $client->charges()->where('status','open')->sum('amount'), 'paid' => $client->charges()->where('status','paid')->sum('amount')];
        return view('admin.clients.show', compact('client','summary'));
    }
    public function edit(Client $client) { return view('admin.clients.form', compact('client')); }
    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request, $client);
        $client->update($data);
        $client->user?->update(['name'=>$data['name'],'email'=>$data['email'],'cpf'=>$data['cpf'],'phone'=>$data['phone'], ...(!empty($data['password']) ? ['password'=>$data['password']] : [])]);
        return redirect()->route('admin.clients.show', $client)->with('success', 'Cliente atualizado.');
    }
    public function destroy(Client $client) { abort_if($client->leases()->exists(), 422, 'Cliente possui aluguéis vinculados.'); $client->user?->delete(); $client->delete(); return redirect()->route('admin.clients.index')->with('success','Cliente excluído.'); }
    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name'=>['required','string','max:255'], 'phone'=>['required','string','max:20'],
            'cpf'=>['required','string','max:14',Rule::unique('clients')->ignore($client)],
            'email'=>['nullable','email','max:255',Rule::unique('users','email')->ignore($client?->user_id)],
            'family_income'=>['nullable','numeric','min:0'], 'status'=>['required',Rule::in(['pending','active','inactive','rejected'])],
            'password'=>[$client ? 'nullable':'required','nullable','confirmed','min:8'],
        ]);
    }
}

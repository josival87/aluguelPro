<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicPropertyController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type', 'residential')->toString();
        $neighborhood = $request->string('neighborhood')->toString();
        $base = Property::query()->where('status', 'available')->where('type', $type);
        $neighborhoods = (clone $base)->select('neighborhood')->selectRaw('COUNT(*) as total')->groupBy('neighborhood')->orderBy('neighborhood')->get();
        $properties = $base->when($neighborhood, fn ($query) => $query->where('neighborhood', $neighborhood))
            ->with(['photos', 'features', 'group'])->latest()->paginate(9)->withQueryString();
        return view('public.properties.index', compact('properties', 'neighborhoods', 'type', 'neighborhood'));
    }

    public function show(Property $property)
    {
        abort_unless($property->status === 'available', 404);
        $property->load('photos', 'features', 'group');
        return view('public.properties.show', compact('property'));
    }

    public function application(Property $property)
    {
        abort_unless($property->status === 'available', 404);
        abort_unless($property->contract_id, 422, 'Este imóvel ainda não possui um tipo de contrato configurado.');
        return view('public.properties.apply', compact('property'));
    }

    public function apply(Request $request, Property $property, WhatsAppService $whatsApp, ContractService $contracts)
    {
        abort_unless($property->status === 'available', 422, 'Este imóvel não está mais disponível.');
        abort_unless($property->contract_id, 422, 'Este imóvel ainda não possui um tipo de contrato configurado.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'cpf' => ['required', 'string', 'max:14', Rule::unique('clients', 'cpf')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'family_income' => ['required', 'numeric', 'min:0'],
            'password' => ['required', 'confirmed', 'min:8'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'privacy' => ['accepted'],
        ]);

        [$client, $lease] = DB::transaction(function () use ($data, $request, $property, $contracts) {
            $login = Str::lower($data['email']);
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'login' => $login,
                'cpf' => $data['cpf'], 'phone' => $data['phone'], 'role' => 'client',
                'active' => true, 'password' => $data['password'],
            ]);
            $client = Client::create([
                'user_id' => $user->id, 'name' => $data['name'], 'phone' => $data['phone'],
                'cpf' => $data['cpf'], 'email' => $data['email'], 'family_income' => $data['family_income'],
                'status' => 'pending',
            ]);
            $file = $request->file('document');
            $client->documents()->create([
                'type' => 'identification', 'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'document_base64' => base64_encode(file_get_contents($file->getRealPath())),
            ]);
            $lease = Lease::create([
                'property_id' => $property->id, 'client_id' => $client->id,
                'contract_months' => 12, 'rent_amount' => $property->rent_amount,
                'has_solar_energy' => $property->has_solar_energy, 'status' => 'awaiting_completion',
            ]);
            $contracts->generate($lease);
            return [$client, $lease];
        });

        $property->load('group');
        $whatsApp->send(
            $property->group->phone,
            "Novo interessado: {$client->name} candidatou-se ao imóvel {$property->title}. Proposta #{$lease->id}.",
            'new_applicant', 'responsible'
        );

        return redirect()->route('login')->with('success', 'Cadastro concluído! Sua proposta foi enviada. Entre para acompanhar.');
    }
}

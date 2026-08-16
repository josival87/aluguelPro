<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Services\ContractService;
use App\Services\WhatsAppService;
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
            ->with(['media' => fn ($query) => $query->select(PropertyMedia::DISPLAY_COLUMNS), 'features', 'group'])->latest()->paginate(9)->withQueryString();

        return view('public.properties.index', compact('properties', 'neighborhoods', 'type', 'neighborhood'));
    }

    public function show(Property $property)
    {
        abort_unless($property->status === 'available', 404);
        $property->load(['media' => fn ($query) => $query->select(PropertyMedia::DISPLAY_COLUMNS), 'features', 'group']);

        return view('public.properties.show', compact('property'));
    }

    public function application(Property $property)
    {
        abort_unless($property->status === 'available', 404);
        abort_unless($property->contract_id, 422, 'Este imóvel ainda não possui um tipo de contrato configurado.');
        $property->load(['media' => fn ($query) => $query->select(PropertyMedia::DISPLAY_COLUMNS)]);

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
            'rg' => ['required', 'string', 'max:30'],
            'profession' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'family_income' => ['required', 'numeric', 'min:0'],
            'password' => ['required', 'confirmed', 'min:8'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'privacy' => ['accepted'],
        ], [
            'name.required' => 'Informe seu nome completo.',
            'name.max' => 'O nome completo não pode ultrapassar 255 caracteres.',
            'phone.required' => 'Informe seu número de WhatsApp.',
            'phone.max' => 'O número de WhatsApp não pode ultrapassar 20 caracteres.',
            'cpf.required' => 'Informe seu CPF.',
            'cpf.max' => 'O CPF deve ter no máximo 14 caracteres, incluindo pontos e traço.',
            'cpf.unique' => 'Este CPF já possui cadastro. Entre na sua conta para acompanhar sua proposta.',
            'rg.required' => 'Informe seu RG.',
            'rg.max' => 'O RG deve ter no máximo 30 caracteres.',
            'profession.required' => 'Informe sua profissão.',
            'profession.max' => 'A profissão deve ter no máximo 255 caracteres.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Digite um endereço de e-mail válido.',
            'email.max' => 'O e-mail não pode ultrapassar 255 caracteres.',
            'email.unique' => 'Este e-mail já possui cadastro. Entre na sua conta ou use outro e-mail.',
            'family_income.required' => 'Informe sua renda familiar.',
            'family_income.numeric' => 'A renda familiar deve ser informada somente com números.',
            'family_income.min' => 'A renda familiar não pode ser negativa.',
            'password.required' => 'Crie uma senha para acessar sua conta.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere. Digite a mesma senha nos dois campos.',
            'document.required' => 'Anexe um documento de identificação.',
            'document.file' => 'O documento de identificação enviado não é um arquivo válido.',
            'document.mimes' => 'O documento de identificação deve ser um arquivo PDF, JPG ou PNG.',
            'document.max' => 'O documento de identificação não pode ultrapassar 8 MB.',
            'document.uploaded' => 'Não foi possível enviar o documento. Tente novamente com um arquivo de até 8 MB.',
            'privacy.accepted' => 'Você precisa autorizar o tratamento dos dados para enviar a proposta.',
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
                'cpf' => $data['cpf'], 'rg' => $data['rg'], 'profession' => $data['profession'],
                'email' => $data['email'], 'family_income' => $data['family_income'],
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

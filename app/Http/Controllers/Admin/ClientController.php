<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\AdminGroupContext;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()->withCount('leases')->when($request->q, function ($query, $term) {
            $cpf = Cpf::digits($term);

            $query->where(function ($sub) use ($term, $cpf) {
                $sub->whereLike('name', "%{$term}%", caseSensitive: false);
                if ($cpf !== null) {
                    $sub->orWhere('cpf', 'like', "%{$cpf}%");
                }
            });
        })->latest()->paginate(15)->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('admin.clients.form', ['client' => new Client]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['group_id'] = AdminGroupContext::groupId($request->user());
        $files = $request->file('documents', []);
        DB::transaction(function () use ($data, $files) {
            $user = ! empty($data['password']) ? User::create($this->userData($data)) : null;
            $client = Client::create([...$data, 'user_id' => $user?->id, 'status' => $data['status'] ?? 'active']);
            $this->storeDocuments($client, $files);
        });

        return redirect()->route('admin.clients.index')->with('success', 'Cliente cadastrado.');
    }

    public function show(Client $client)
    {
        $client->load(['documents', 'leases.property', 'leases.charges' => fn ($q) => $q->latest('due_date')]);
        $summary = ['open' => $client->charges()->where('status', 'open')->sum('amount'), 'paid' => $client->charges()->where('status', 'paid')->sum('amount')];

        return view('admin.clients.show', compact('client', 'summary'));
    }

    public function edit(Client $client)
    {
        $client->load('documents');

        return view('admin.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request, $client);
        $files = $request->file('documents', []);
        DB::transaction(function () use ($client, $data, $files) {
            $client->update($data);
            if ($client->user) {
                $client->user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'login' => $data['cpf'],
                    'cpf' => $data['cpf'],
                    'phone' => $data['phone'],
                    ...(! empty($data['password']) ? ['password' => $data['password']] : []),
                ]);
            } elseif (! empty($data['password'])) {
                $client->update(['user_id' => User::create($this->userData($data))->id]);
            }
            $this->storeDocuments($client, $files);
        });

        return redirect()->route('admin.clients.show', $client)->with('success', 'Cliente atualizado.');
    }

    public function destroy(Client $client)
    {
        abort_if($client->leases()->exists(), 422, 'Cliente possui aluguéis vinculados.');
        $client->user?->delete();
        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', 'Cliente excluído.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        $request->merge(['cpf' => Cpf::digits($request->input('cpf'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:20'],
            'cpf' => ['required', 'digits:11', Rule::unique('clients')->ignore($client)],
            'rg' => ['required', 'string', 'max:30'],
            'profession' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client?->user_id)],
            'family_income' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ], [
            'cpf.digits' => 'O CPF deve conter 11 números.',
            'rg.required' => 'Informe o RG do cliente.',
            'rg.max' => 'O RG deve ter no máximo 30 caracteres.',
            'profession.max' => 'A profissão deve ter no máximo 255 caracteres.',
            'documents.array' => 'Selecione arquivos válidos para os documentos.',
            'documents.max' => 'Envie no máximo 5 documentos por vez.',
            'documents.*.file' => 'Um dos documentos enviados não é um arquivo válido.',
            'documents.*.mimes' => 'Os documentos devem estar em PDF, JPG ou PNG.',
            'documents.*.max' => 'Cada documento pode ter no máximo 8 MB.',
            'documents.*.uploaded' => 'Não foi possível enviar um dos documentos. Tente novamente com um arquivo de até 8 MB.',
        ]);

        unset($data['documents']);

        return $data;
    }

    private function userData(array $data): array
    {
        return [
            'name' => $data['name'], 'email' => $data['email'],
            'login' => $data['cpf'],
            'cpf' => $data['cpf'], 'phone' => $data['phone'], 'role' => 'client',
            'password' => $data['password'],
        ];
    }

    /** @param array<int, UploadedFile> $files */
    private function storeDocuments(Client $client, array $files): void
    {
        foreach ($files as $file) {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                throw ValidationException::withMessages([
                    'documents' => 'Não foi possível ler um dos documentos enviados.',
                ]);
            }

            $fileName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $fileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?: 'documento';

            $client->documents()->create([
                'type' => 'identification',
                'original_name' => Str::limit(trim($fileName), 240, ''),
                'mime_type' => Str::limit($file->getMimeType() ?: 'application/octet-stream', 100, ''),
                'document_base64' => base64_encode($contents),
            ]);
        }
    }
}

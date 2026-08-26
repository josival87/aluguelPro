<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ClientProfileController extends Controller
{
    public function edit(Request $request)
    {
        $client = $this->authenticatedClient($request);
        $client->load('documents');

        return view('client.profile', compact('client'));
    }

    public function update(Request $request)
    {
        $client = $this->authenticatedClient($request);
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'rg' => ['required', 'string', 'max:30'],
            'profession' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'family_income' => ['nullable', 'numeric', 'min:0'],
        ], [
            'rg.required' => 'Informe o RG.',
            'rg.max' => 'O RG deve ter no máximo 30 caracteres.',
            'profession.max' => 'A profissão deve ter no máximo 255 caracteres.',
        ]);

        DB::transaction(function () use ($client, $data, $request): void {
            $client->update($data);
            $request->user()->update([
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
            ]);
        });

        return redirect()->route('client.profile.edit')->with('success', 'Dados pessoais atualizados.');
    }

    public function storeDocument(Request $request)
    {
        $client = $this->authenticatedClient($request);
        $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ], [
            'documents.required' => 'Selecione pelo menos um documento.',
            'documents.array' => 'Selecione arquivos válidos para os documentos.',
            'documents.max' => 'Envie no máximo 5 documentos por vez.',
            'documents.*.file' => 'Um dos documentos enviados não é um arquivo válido.',
            'documents.*.mimes' => 'Os documentos devem estar em PDF, JPG ou PNG.',
            'documents.*.max' => 'Cada documento pode ter no máximo 8 MB.',
            'documents.*.uploaded' => 'Não foi possível enviar um dos documentos. Tente novamente com um arquivo de até 8 MB.',
        ]);

        DB::transaction(function () use ($client, $request): void {
            foreach ($request->file('documents', []) as $file) {
                $this->storeDocumentFile($client, $file);
            }
        });

        $count = count($request->file('documents', []));

        return redirect()->route('client.profile.edit')
            ->with('success', $count.' documento(s) enviado(s).');
    }

    public function showDocument(Request $request, ClientDocument $document)
    {
        $client = $this->authenticatedClient($request);
        abort_unless($document->client_id === $client->id, 404);

        $contents = base64_decode($document->document_base64, true);
        abort_if($contents === false, 500, 'O documento armazenado está corrompido.');

        $fileName = $this->safeFileName($document->original_name);
        $fallbackName = preg_replace('/[^A-Za-z0-9._ -]/', '', Str::ascii($fileName)) ?: 'documento';
        $mimeType = in_array($document->mime_type, [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ], true) ? $document->mime_type : 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $fileName,
                $fallbackName,
            ),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authenticatedClient(Request $request): Client
    {
        $client = $request->user()->client;
        abort_unless($client, 403);

        return $client;
    }

    private function storeDocumentFile(Client $client, UploadedFile $file): void
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'documents' => 'Não foi possível ler um dos documentos enviados.',
            ]);
        }

        $client->documents()->create([
            'type' => 'identification',
            'original_name' => $this->safeFileName($file->getClientOriginalName()),
            'mime_type' => Str::limit($file->getMimeType() ?: 'application/octet-stream', 100, ''),
            'document_base64' => base64_encode($contents),
        ]);
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';

        return Str::limit(trim($name) ?: 'documento', 240, '');
    }
}

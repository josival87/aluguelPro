<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\LeaseDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaseDocumentController extends Controller
{
    private const CATEGORIES = [
        'legacy_contract', 'addendum', 'inspection', 'receipt', 'other',
    ];

    public function store(Request $request, Lease $lease)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'max:10240'],
        ], [
            'documents.required' => 'Selecione pelo menos um documento.',
            'documents.max' => 'Envie no máximo 5 documentos por vez.',
            'documents.*.max' => 'Cada documento pode ter no máximo 10 MB.',
        ]);

        DB::transaction(function () use ($request, $lease, $data) {
            foreach ($request->file('documents', []) as $file) {
                $contents = file_get_contents($file->getRealPath());

                if ($contents === false) {
                    throw ValidationException::withMessages([
                        'documents' => 'Não foi possível ler um dos documentos enviados.',
                    ]);
                }

                $lease->documents()->create([
                    'uploaded_by' => $request->user()->id,
                    'category' => $data['category'],
                    'original_name' => $this->safeFileName($file->getClientOriginalName()),
                    'mime_type' => Str::limit($file->getMimeType() ?: 'application/octet-stream', 150, ''),
                    'size_bytes' => strlen($contents),
                    'checksum_sha256' => hash('sha256', $contents),
                    'description' => $data['description'] ?? null,
                    'document_base64' => base64_encode($contents),
                ]);
            }
        });

        return redirect()->route('admin.leases.show', $lease)
            ->with('success', count($request->file('documents', [])).' documento(s) anexado(s) ao aluguel.');
    }

    public function download(Lease $lease, LeaseDocument $document)
    {
        $this->ensureDocumentBelongsToLease($lease, $document);

        $contents = base64_decode($document->document_base64, true);
        abort_if(
            $contents === false || ! hash_equals($document->checksum_sha256, hash('sha256', $contents)),
            500,
            'O documento armazenado está corrompido.'
        );

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'Content-Length' => (string) strlen($contents),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function ensureDocumentBelongsToLease(Lease $lease, LeaseDocument $document): void
    {
        abort_unless($document->lease_id === $lease->id, 404);
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';

        return Str::limit(trim($name) ?: 'documento', 240, '');
    }
}

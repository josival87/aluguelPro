<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ClientDocumentController extends Controller
{
    public function show(Client $client, ClientDocument $document)
    {
        abort_unless($document->client_id === $client->id, 404);

        $contents = base64_decode($document->document_base64, true);
        abort_if($contents === false, 500, 'O documento armazenado está corrompido.');

        $fileName = basename(str_replace('\\', '/', $document->original_name));
        $fileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?: 'documento';
        $fallbackName = Str::ascii($fileName);
        $fallbackName = preg_replace('/[^A-Za-z0-9._ -]/', '', $fallbackName) ?: 'documento';
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

    public function destroy(Client $client, ClientDocument $document)
    {
        abort_unless($document->client_id === $client->id, 404);

        $document->delete();

        return redirect()->route('admin.clients.edit', $client)
            ->with('success', 'Documento removido.');
    }
}

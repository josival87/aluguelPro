<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyMediaController extends Controller
{
    public function store(Request $request, Property $property)
    {
        $request->validate([
            'media' => ['required', 'array', 'max:'.PropertyMedia::MAX_ITEMS],
            'media.*' => [
                'required',
                'file',
                'mimetypes:'.implode(',', PropertyMedia::ALLOWED_MIME_TYPES),
                'max:'.PropertyMedia::MAX_SIZE_KB,
            ],
        ], [
            'media.required' => 'Selecione ao menos uma mídia.',
            'media.max' => 'Envie no máximo '.PropertyMedia::MAX_ITEMS.' mídias por imóvel.',
            'media.*.mimetypes' => 'Cada mídia deve ser uma imagem ou um vídeo compatível.',
            'media.*.max' => 'Cada mídia pode ter no máximo 50 MB.',
        ]);

        $files = $request->file('media', []);
        $currentCount = $property->media()->count();

        if ($currentCount + count($files) > PropertyMedia::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'media' => 'O imóvel pode ter no máximo '.PropertyMedia::MAX_ITEMS.' mídias.',
            ]);
        }

        $nextSortOrder = ((int) $property->media()->max('sort_order')) + 1;

        DB::transaction(function () use ($files, $nextSortOrder, $property): void {
            foreach ($files as $index => $file) {
                $contents = file_get_contents($file->getRealPath());

                abort_if($contents === false, 422, 'Não foi possível processar uma das mídias.');

                $property->media()->create([
                    'mime_type' => PropertyMedia::normalizeMimeType($file->getMimeType()),
                    'media_base64' => base64_encode($contents),
                    'sort_order' => $nextSortOrder + $index,
                ]);
            }
        });

        return back()->with('success', count($files) === 1 ? 'Mídia adicionada.' : 'Mídias adicionadas.');
    }

    public function destroy(Property $property, PropertyMedia $propertyMedia)
    {
        abort_unless($propertyMedia->property_id === $property->id, 404);

        $propertyMedia->delete();

        return back()->with('success', 'Mídia removida.');
    }
}

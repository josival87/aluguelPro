<?php

namespace App\Http\Controllers;

use App\Models\PropertyMedia;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PropertyMediaController extends Controller
{
    public function show(Request $request, PropertyMedia $propertyMedia): Response
    {
        $contents = base64_decode($propertyMedia->media_base64, true);

        abort_if($contents === false, 404);

        $size = strlen($contents);
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => $propertyMedia->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($propertyMedia->is_video && preg_match('/bytes=(\d*)-(\d*)/', (string) $request->header('Range'), $matches)) {
            $start = $matches[1] === '' ? 0 : (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);

            abort_if($start > $end || $start >= $size, 416);

            $chunk = substr($contents, $start, $end - $start + 1);
            $headers['Content-Length'] = (string) strlen($chunk);
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            return response($chunk, 206, $headers);
        }

        $headers['Content-Length'] = (string) $size;

        return response($contents, 200, $headers);
    }
}

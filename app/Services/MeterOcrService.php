<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MeterOcrService
{
    /** @return array{reading: float|null, confidence: float, candidates: array, requires_confirmation: bool} */
    public function read(UploadedFile $photo): array
    {
        $response = Http::timeout(config('services.meter_ocr.timeout'))
            ->attach('file', fopen($photo->getRealPath(), 'r'), $photo->getClientOriginalName())
            ->post(rtrim(config('services.meter_ocr.url'), '/').'/read-meter');

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível analisar a foto agora. Informe a leitura manualmente.');
        }

        $result = $response->json();
        $confidence = (float) ($result['confidence'] ?? 0);
        $suggestedReading = isset($result['reading']) ? (float) $result['reading'] : null;
        $minimumConfidence = (float) config('services.meter_ocr.min_confidence', 0.70);
        $acceptedReading = $confidence >= $minimumConfidence ? $suggestedReading : null;

        return [
            // Uma sugestão fraca nunca deve preencher o valor que gera cobrança.
            'reading' => $acceptedReading,
            'confidence' => $confidence,
            'candidates' => $result['candidates'] ?? [],
            'requires_confirmation' => true,
            'message' => $acceptedReading === null && $suggestedReading !== null
                ? 'O visor foi detectado, mas a confiança da leitura ficou baixa. Confira a foto e informe o valor manualmente.'
                : null,
        ];
    }
}

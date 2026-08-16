<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

class ContractDateExtractor
{
    /** @return array{start_date: ?string, end_date: ?string} */
    public function extract(?string $content): array
    {
        $empty = ['start_date' => null, 'end_date' => null];

        if (! filled($content)) {
            return $empty;
        }

        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $patterns = [
            '/iniciando-se\s+em\s+(.+?)\s*,?\s*para\s+terminar\s+em\s+(.+?)(?:\s*[;.]|$)/iu',
            '/vigorar[aá]\s+de\s+(.+?)\s+a\s+(.+?)(?:\s*,|\s*[;.]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $text, $matches)) {
                continue;
            }

            $startDate = $this->parseDate($matches[1]);
            $endDate = $this->parseDate($matches[2]);

            if ($startDate && $endDate) {
                return ['start_date' => $startDate, 'end_date' => $endDate];
            }
        }

        return $empty;
    }

    private function parseDate(string $value): ?string
    {
        if (preg_match('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\b/u', $value, $matches)) {
            return $this->safeDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (! preg_match('/\b(\d{1,2})\s+de\s+([\p{L}]+)\s+de\s+(\d{4})\b/iu', $value, $matches)) {
            return null;
        }

        $month = [
            'janeiro' => 1,
            'fevereiro' => 2,
            'marco' => 3,
            'abril' => 4,
            'maio' => 5,
            'junho' => 6,
            'julho' => 7,
            'agosto' => 8,
            'setembro' => 9,
            'outubro' => 10,
            'novembro' => 11,
            'dezembro' => 12,
        ][Str::ascii(mb_strtolower($matches[2]))] ?? null;

        return $month ? $this->safeDate((int) $matches[3], $month, (int) $matches[1]) : null;
    }

    private function safeDate(int $year, int $month, int $day): ?string
    {
        try {
            return Carbon::createSafe($year, $month, $day)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}

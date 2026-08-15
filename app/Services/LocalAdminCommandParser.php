<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Str;

class LocalAdminCommandParser
{
    private const MONTHS = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4,
        'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8,
        'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];

    /** @return array{tool: string, arguments: array<string, mixed>}|null */
    public function parse(string $prompt): ?array
    {
        $normalized = $this->normalize($prompt);
        $intent = $this->detectIntent($normalized);
        if ($intent === null) {
            return null;
        }

        [$month, $year] = $this->detectPeriod($normalized);
        $propertyTitle = $this->detectPropertyTitle($normalized);
        $chargeType = str_contains($normalized, 'solar') || str_contains($normalized, 'energia')
            ? 'solar'
            : (str_contains($normalized, 'aluguel') || str_contains($normalized, 'locacao') ? 'rent' : null);

        if (in_array($intent, ['settle_charge', 'reopen_charge'], true)) {
            if ($month === null || $propertyTitle === null) {
                return null;
            }
            return ['tool' => $intent, 'arguments' => [
                'property_title' => $propertyTitle,
                'month' => $month,
                'year' => $year,
                'charge_type' => $chargeType,
            ]];
        }

        if ($intent === 'financial_summary') {
            return ['tool' => $intent, 'arguments' => [
                'month' => $month ?? now()->month,
                'year' => $year,
                'property_title' => $propertyTitle,
            ]];
        }

        $status = str_contains($normalized, 'vencid')
            ? 'overdue'
            : (str_contains($normalized, 'pag') || str_contains($normalized, 'baixad') ? 'paid' : 'open');

        return ['tool' => 'list_charges', 'arguments' => [
            'month' => $month ?? now()->month,
            'year' => $year,
            'property_title' => $propertyTitle,
            'charge_type' => $chargeType,
            'status' => $status,
        ]];
    }

    public function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($value))));
    }

    private function detectIntent(string $prompt): ?string
    {
        if (preg_match('/\b(dar baixa|baixar|registrar (o )?pagamento|marcar como pag[oa]|confirmar (o )?pagamento)\b/', $prompt)) {
            return 'settle_charge';
        }
        if (preg_match('/\b(reabrir|estornar|desfazer (a )?baixa|cancelar (o )?pagamento)\b/', $prompt)) {
            return 'reopen_charge';
        }
        if (preg_match('/\b(resumo|balanco|total|totais|recebido|receita)\b/', $prompt)) {
            return 'financial_summary';
        }
        if (preg_match('/\b(listar|liste|mostrar|mostre|quais|cobrancas?|pagamentos?)\b/', $prompt)) {
            return 'list_charges';
        }
        return null;
    }

    /** @return array{0: ?int, 1: int} */
    private function detectPeriod(string $prompt): array
    {
        $month = null;
        foreach (self::MONTHS as $name => $number) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/', $prompt)) {
                $month = $number;
                break;
            }
        }
        if ($month === null && preg_match('/\b(0?[1-9]|1[0-2])[\/\-](20\d{2})\b/', $prompt, $matches)) {
            $month = (int) $matches[1];
        }
        preg_match('/\b(20\d{2})\b/', $prompt, $yearMatch);
        return [$month, isset($yearMatch[1]) ? (int) $yearMatch[1] : now()->year];
    }

    private function detectPropertyTitle(string $prompt): ?string
    {
        return Property::query()->orderByDesc('id')->get(['title'])
            ->filter(fn (Property $property) => str_contains($prompt, $this->normalize($property->title)))
            ->sortByDesc(fn (Property $property) => mb_strlen($this->normalize($property->title)))
            ->first()?->title;
    }
}

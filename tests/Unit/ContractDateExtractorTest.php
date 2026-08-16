<?php

namespace Tests\Unit;

use App\Services\ContractDateExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContractDateExtractorTest extends TestCase
{
    #[DataProvider('contractContents')]
    public function test_it_extracts_start_and_end_dates_from_contract_text(string $content): void
    {
        $this->assertSame([
            'start_date' => '2026-08-14',
            'end_date' => '2027-08-14',
        ], app(ContractDateExtractor::class)->extract($content));
    }

    /** @return array<string, array{string}> */
    public static function contractContents(): array
    {
        return [
            'datas por extenso' => [
                '<p>O prazo da presente locação, iniciando-se em 14 de Agosto de 2026, para terminar em 14 de Agosto de 2027;</p>',
            ],
            'datas numéricas' => [
                '<p>A locação vigorará de 14/08/2026 a 14/08/2027, pelo prazo de 12 meses.</p>',
            ],
        ];
    }

    public function test_it_does_not_guess_unrelated_dates(): void
    {
        $this->assertSame([
            'start_date' => null,
            'end_date' => null,
        ], app(ContractDateExtractor::class)->extract('<p>Documento gerado em 14/08/2026.</p>'));
    }
}

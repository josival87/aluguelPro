<?php

namespace Tests\Unit;

use App\Services\MeterOcrService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeterOcrServiceTest extends TestCase
{
    public function test_it_never_accepts_a_low_confidence_reading_for_billing(): void
    {
        config(['services.meter_ocr.url' => 'http://ocr.test', 'services.meter_ocr.min_confidence' => 0.70]);
        Http::fake(['http://ocr.test/read-meter' => Http::response([
            'reading' => 83,
            'confidence' => 0.05,
            'candidates' => [['value' => 83, 'confidence' => 0.05]],
            'requires_confirmation' => true,
        ])]);

        $result = app(MeterOcrService::class)->read(UploadedFile::fake()->createWithContent('meter.jpeg', 'jpeg-test-payload'));

        $this->assertNull($result['reading']);
        $this->assertSame(0.05, $result['confidence']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertNotNull($result['message']);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://ocr.test/read-meter');
    }

    public function test_it_returns_a_high_confidence_suggestion_but_still_requires_confirmation(): void
    {
        config(['services.meter_ocr.url' => 'http://ocr.test', 'services.meter_ocr.min_confidence' => 0.70]);
        Http::fake(['http://ocr.test/read-meter' => Http::response([
            'reading' => 517,
            'confidence' => 0.91,
            'candidates' => [],
            'requires_confirmation' => true,
        ])]);

        $result = app(MeterOcrService::class)->read(UploadedFile::fake()->createWithContent('meter.jpeg', 'jpeg-test-payload'));

        $this->assertSame(517.0, $result['reading']);
        $this->assertTrue($result['requires_confirmation']);
    }
}

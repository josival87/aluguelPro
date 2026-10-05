<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ContractEditorToolbarTest extends TestCase
{
    public function test_toolbar_offers_controls_to_decrease_and_increase_the_font_size(): void
    {
        $toolbar = Blade::render('<x-contract-editor-toolbar />');

        $this->assertStringContainsString('data-font-adjust="-1"', $toolbar);
        $this->assertStringContainsString('aria-label="Diminuir tamanho da fonte"', $toolbar);
        $this->assertStringContainsString('data-font-adjust="1"', $toolbar);
        $this->assertStringContainsString('aria-label="Aumentar tamanho da fonte"', $toolbar);
    }
}

<?php

namespace Tests\Unit;

use App\Services\ContractService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractTemplateServiceTest extends TestCase
{
    public function test_it_accepts_supported_variables_and_removes_unsafe_html(): void
    {
        $service = app(ContractService::class);
        $content = $service->validateTemplate('<p onclick="alert(1)">Olá {{nome_cliente}}</p><script>alert(2)</script>');

        $this->assertStringContainsString('{{nome_cliente}}', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    public function test_it_rejects_an_unknown_variable(): void
    {
        $this->expectException(ValidationException::class);

        app(ContractService::class)->validateTemplate('<p>{{variavel_inexistente}}</p>');
    }

    public function test_it_preserves_editor_layout_and_removes_unsafe_styles(): void
    {
        $content = app(ContractService::class)->sanitize(
            '<p style="text-align: center; margin-left: 40px; color: red; background: url(javascript:alert(1))">Cláusula</p>'
        );

        $this->assertStringContainsString('text-align: center', $content);
        $this->assertStringContainsString('margin-left: 40px', $content);
        $this->assertStringNotContainsString('color:', $content);
        $this->assertStringNotContainsString('background:', $content);
        $this->assertStringNotContainsString('javascript:', $content);
    }

    public function test_it_accepts_an_unquoted_style_attribute_from_the_editor(): void
    {
        $content = app(ContractService::class)->sanitize(
            '<p style=text-align:center>Cláusula centralizada</p>'
        );

        $this->assertSame(
            '<p style="text-align: center">Cláusula centralizada</p>',
            $content,
        );
    }

    public function test_it_preserves_supported_font_sizes_and_removes_arbitrary_ones(): void
    {
        $content = app(ContractService::class)->sanitize(
            '<p><font size="2">Texto menor legado</font><span style="font-size: 13px">Texto menor</span><span style="font-size: 99px">Texto inseguro</span></p>'
        );

        $this->assertStringContainsString('<span style="font-size: 13px">Texto menor legado</span>', $content);
        $this->assertStringContainsString('<span style="font-size: 13px">Texto menor</span>', $content);
        $this->assertStringContainsString('<span>Texto inseguro</span>', $content);
        $this->assertStringNotContainsString('99px', $content);
    }
}

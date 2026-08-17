<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseProxyPrefixTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_include_the_trusted_reverse_proxy_prefix(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.10'])
            ->withHeaders([
                'X-Forwarded-Host' => 'jbmj.io',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Prefix' => '/alugapro',
            ])
            ->get('/');

        $response->assertOk()
            ->assertSee('https://jbmj.io/alugapro/entrar', false)
            ->assertSee('https://jbmj.io/alugapro/css/app.css', false);
    }
}

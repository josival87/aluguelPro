<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMobileLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mobile_header_has_logout_action_in_account_menu(): void
    {
        $admin = User::factory()->create([
            'name' => 'Administrador Teste',
            'role' => 'admin',
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('mobile-account-menu', false)
            ->assertSee('Abrir menu da conta')
            ->assertSee('Área administrativa')
            ->assertSeeText('Sair');

        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('logout').'"'));
    }
}

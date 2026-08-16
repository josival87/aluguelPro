<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLogoutVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_area_has_logout_actions_for_desktop_and_mobile(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'active' => true,
        ]);

        Client::create([
            'user_id' => $user->id,
            'name' => 'Cliente Teste',
            'phone' => '81999990000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente@example.test',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response
            ->assertOk()
            ->assertSee('client-desktop-logout', false)
            ->assertSee('client-nav', false)
            ->assertSeeText('Sair');

        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('logout').'"'));
    }

    public function test_client_can_log_out(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}

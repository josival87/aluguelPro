<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_entry_link_opens_the_login_page(): void
    {
        $this->get(route('properties.index'))
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSeeText('Entrar');

        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('CPF do cliente ou login da equipe');
    }

    public function test_authenticated_user_is_sent_to_their_area_instead_of_back_to_the_public_home(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertSeeText('Minha área')
            ->assertSee('href="'.route('admin.dashboard').'"', false);

        $this->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));

        auth()->logout();
        $client = $this->createClientUser();

        $this->actingAs($client)
            ->get(route('login'))
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_client_can_log_in_with_cpf_with_or_without_punctuation(): void
    {
        $user = $this->createClientUser();

        $this->post(route('login.store'), [
            'login' => '12345678900',
            'password' => 'senha-segura',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->post(route('login.store'), [
            'login' => '123.456.789-00',
            'password' => 'senha-segura',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_client_email_is_not_accepted_as_login(): void
    {
        $this->createClientUser();

        $this->from(route('login'))->post(route('login.store'), [
            'login' => 'cliente@example.test',
            'password' => 'senha-segura',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_staff_keeps_using_their_regular_login(): void
    {
        $user = User::factory()->create([
            'login' => 'administrador',
            'role' => 'admin',
            'password' => 'senha-segura',
        ]);

        $this->post(route('login.store'), [
            'login' => 'administrador',
            'password' => 'senha-segura',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_can_use_an_eleven_digit_numeric_login(): void
    {
        $user = User::factory()->create([
            'login' => '11122233344',
            'role' => 'admin',
            'password' => 'senha-segura',
        ]);

        $this->post(route('login.store'), [
            'login' => '11122233344',
            'password' => 'senha-segura',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    private function createClientUser(): User
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.test',
            'login' => 'cliente@example.test',
            'cpf' => '123.456.789-00',
            'role' => 'client',
            'active' => true,
            'password' => 'senha-segura',
        ]);

        Client::create([
            'user_id' => $user->id,
            'name' => 'Cliente Teste',
            'phone' => '81999990000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente@example.test',
            'status' => 'active',
        ]);

        return $user;
    }
}

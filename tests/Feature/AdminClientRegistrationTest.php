<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_a_client_without_a_password_or_portal_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.clients.create'))
            ->assertOk()
            ->assertSee('name="rg"', false)
            ->assertSee('name="profession"', false)
            ->assertSee('Senha (opcional)')
            ->assertDontSee('name="password" required', false);

        $this->actingAs($admin)
            ->post(route('admin.clients.store'), $this->clientData())
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHasNoErrors();

        $client = Client::query()->sole();

        $this->assertNull($client->user_id);
        $this->assertSame('12.345.678-9 SSP/PE', $client->rg);
        $this->assertSame('Analista de sistemas', $client->profession);
        $this->assertSame(1, User::query()->count());
    }

    public function test_admin_can_create_portal_access_later_by_setting_the_client_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $client = Client::create($this->clientData());

        $this->actingAs($admin)
            ->put(route('admin.clients.update', $client), [
                ...$this->clientData(),
                'password' => 'Cliente@2026',
                'password_confirmation' => 'Cliente@2026',
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($client->fresh()->user_id);
        $this->assertDatabaseHas('users', [
            'email' => 'cliente@example.test',
            'role' => 'client',
        ]);
    }

    /** @return array<string, mixed> */
    private function clientData(): array
    {
        return [
            'name' => 'Cliente sem senha',
            'phone' => '81999990000',
            'cpf' => '123.456.789-00',
            'rg' => '12.345.678-9 SSP/PE',
            'profession' => 'Analista de sistemas',
            'email' => 'cliente@example.test',
            'family_income' => 5000,
            'status' => 'active',
        ];
    }
}

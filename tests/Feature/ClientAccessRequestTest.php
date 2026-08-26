<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAccessCode;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_the_request_access_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Solicitar acesso')
            ->assertSee('href="'.route('access.request').'"', false);
    }

    public function test_unregistered_phone_is_rejected_with_the_requested_message(): void
    {
        $this->from(route('access.request'))
            ->post(route('access.send'), ['phone' => '(81) 99999-0000'])
            ->assertRedirect(route('access.request'))
            ->assertSessionHasErrors([
                'phone' => 'O número informado não está vinculado a nenhum cliente.',
            ]);

        $this->assertDatabaseCount('client_access_codes', 0);
    }

    public function test_client_without_user_can_confirm_otp_and_create_a_password(): void
    {
        $client = Client::create([
            'name' => 'Cliente sem acesso',
            'phone' => '(81) 98765-4321',
            'cpf' => '12345678900',
            'email' => 'cliente@example.test',
            'status' => 'active',
        ]);

        $delivery = new NotificationLog(['status' => 'sent']);
        $this->mock(WhatsAppService::class, function (MockInterface $mock) use ($delivery) {
            $mock->shouldReceive('send')
                ->once()
                ->with(
                    '5581987654321',
                    \Mockery::on(fn (string $message) => str_contains($message, 'código para solicitar acesso')),
                    'client_access_otp',
                    'client',
                )
                ->andReturn($delivery);
        });

        $sendResponse = $this->post(route('access.send'), [
            'phone' => '+55 81 98765-4321',
        ]);

        $sendResponse
            ->assertRedirect(route('access.code.form'))
            ->assertSessionHas('client_access.client_id', $client->id)
            ->assertSessionHas('client_access.code_id');

        $accessCode = ClientAccessCode::query()->sole();
        $knownCode = '482913';
        $accessCode->update([
            'code_hash' => hash_hmac('sha256', $knownCode, (string) config('app.key')),
        ]);

        $this->get(route('access.code.form'))
            ->assertOk()
            ->assertSeeText('Confirme o código')
            ->assertSeeText('4321');

        $this->post(route('access.code.verify'), ['code' => $knownCode])
            ->assertRedirect(route('access.password.form'));

        $this->get(route('access.password.form'))
            ->assertOk()
            ->assertSeeText('Cadastre sua senha');

        $this->post(route('access.password.store'), [
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $client->refresh();
        $this->assertNotNull($client->user_id);
        $this->assertTrue(Hash::check('nova-senha-segura', $client->user->password));
        $this->assertNotNull($accessCode->refresh()->used_at);

        $this->post(route('login.store'), [
            'login' => '12345678900',
            'password' => 'nova-senha-segura',
        ])->assertRedirect(route('client.dashboard'));
    }

    public function test_verified_client_can_replace_the_existing_password(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'cpf' => '98765432100',
            'password' => 'senha-antiga',
        ]);
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Cliente existente',
            'phone' => '81999998888',
            'cpf' => '98765432100',
            'status' => 'active',
        ]);
        $code = ClientAccessCode::create([
            'client_id' => $client->id,
            'phone' => '5581999998888',
            'code_hash' => hash_hmac('sha256', '123456', (string) config('app.key')),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        $this->withSession([
            'client_access.client_id' => $client->id,
            'client_access.code_id' => $code->id,
            'client_access.verified_code_id' => $code->id,
        ])->post(route('access.password.store'), [
            'password' => 'senha-atualizada',
            'password_confirmation' => 'senha-atualizada',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('senha-atualizada', $user->refresh()->password));
    }
}

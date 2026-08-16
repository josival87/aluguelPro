<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppSetting;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_encrypted_wppconnect_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'api_url' => 'https://wppconnect.example.test/',
            'session_name' => 'alugapro',
            'secret_key' => 'super-secret-key',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('https://wppconnect.example.test', $setting->api_url);
        $this->assertSame('alugapro', $setting->session_name);
        $this->assertSame('super-secret-key', $setting->secret_key);
        $this->assertNotSame(
            'super-secret-key',
            DB::table('whatsapp_settings')->value('secret_key'),
        );

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Configuração do WhatsApp')
            ->assertSee('Conectar WhatsApp');
    }

    public function test_connect_generates_token_starts_session_and_returns_qr_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->configuredSetting(['api_token' => null]);
        $qrCode = 'data:image/png;base64,'.base64_encode(str_repeat('qr-image', 30));

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($qrCode) {
            if (str_ends_with($request->url(), '/api/alugapro/server-secret/generate-token')) {
                return Http::response(['status' => 'Success', 'token' => 'generated-jwt'], 201);
            }

            if (str_ends_with($request->url(), '/api/alugapro/start-session')) {
                return Http::response(['status' => 'QRCODE', 'qrcode' => $qrCode]);
            }

            if (str_ends_with($request->url(), '/api/alugapro/check-connection-session')) {
                return Http::response(['status' => false, 'message' => 'Disconnected']);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.whatsapp.connect'))
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_qr')
            ->assertJsonPath('connected', false)
            ->assertJsonPath('qr_code', $qrCode);

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('generated-jwt', $setting->api_token);
        $this->assertSame('awaiting_qr', $setting->connection_status);

        Http::assertSent(function (ClientRequest $request): bool {
            return str_ends_with($request->url(), '/api/alugapro/start-session')
                && $request->hasHeader('Authorization', 'Bearer generated-jwt')
                && $request['waitQrCode'] === true;
        });
    }

    public function test_connect_uses_environment_defaults_before_a_database_setting_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config()->set('services.wppconnect.url', 'https://wppconnect.example.test');
        config()->set('services.wppconnect.session', 'alugapro');
        config()->set('services.wppconnect.secret_key', 'environment-secret');
        $qrCode = 'data:image/png;base64,'.base64_encode(str_repeat('environment-qr', 20));

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($qrCode) {
            if (str_ends_with($request->url(), '/api/alugapro/environment-secret/generate-token')) {
                return Http::response(['token' => 'environment-jwt'], 201);
            }

            if (str_ends_with($request->url(), '/api/alugapro/start-session')) {
                return Http::response(['status' => 'QRCODE', 'qrcode' => $qrCode]);
            }

            if (str_ends_with($request->url(), '/api/alugapro/check-connection-session')) {
                return Http::response(['status' => false]);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.whatsapp.connect'))
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_qr')
            ->assertJsonPath('qr_code', $qrCode);

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('https://wppconnect.example.test', $setting->api_url);
        $this->assertSame('environment-secret', $setting->secret_key);
        $this->assertSame('environment-jwt', $setting->api_token);
    }

    public function test_status_detects_connection_and_records_connected_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->configuredSetting(['api_token' => 'stored-jwt', 'connection_status' => 'awaiting_qr']);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/api/alugapro/check-connection-session')) {
                return Http::response(['status' => true, 'message' => 'Connected']);
            }

            if (str_ends_with($request->url(), '/api/alugapro/get-phone-number')) {
                return Http::response(['response' => '5581987656944@c.us']);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $this->actingAs($admin)
            ->getJson(route('admin.whatsapp.status'))
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('phone', '5581987656944');

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('connected', $setting->connection_status);
        $this->assertSame('5581987656944', $setting->connected_phone);
        $this->assertNotNull($setting->last_connected_at);
    }

    public function test_existing_whatsapp_service_sends_text_using_wppconnect_contract(): void
    {
        $this->configuredSetting();

        Http::preventStrayRequests();
        Http::fake([
            'https://wppconnect.example.test/api/alugapro/send-message' => Http::response([
                'status' => 'success',
                'response' => ['id' => ['_serialized' => 'message-123']],
            ]),
        ]);

        $log = app(WhatsAppService::class)->send(
            '+55 (81) 98765-6944',
            'Mensagem de cobrança',
            'due_today',
            'client',
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('message-123', $log->provider_reference);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://wppconnect.example.test/api/alugapro/send-message'
                && $request->hasHeader('Authorization', 'Bearer stored-jwt')
                && $request['phone'] === '5581987656944'
                && $request['isGroup'] === false
                && $request['message'] === 'Mensagem de cobrança';
        });
    }

    public function test_whatsapp_service_sends_image_as_base64(): void
    {
        $this->configuredSetting();

        Http::preventStrayRequests();
        Http::fake([
            'https://wppconnect.example.test/api/alugapro/send-image' => Http::response([
                'status' => 'success',
                'response' => ['id' => 'image-456'],
            ]),
        ]);

        $log = app(WhatsAppService::class)->sendImage(
            '+5581987656944',
            'image-binary-content',
            'vistoria.png',
            'Foto da vistoria',
            'inspection_image',
            'client',
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('image-456', $log->provider_reference);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://wppconnect.example.test/api/alugapro/send-image'
                && $request['phone'] === '5581987656944'
                && $request['filename'] === 'vistoria.png'
                && $request['caption'] === 'Foto da vistoria'
                && $request['base64'] === base64_encode('image-binary-content');
        });
    }

    public function test_whatsapp_configuration_requires_admin_authentication(): void
    {
        $this->get(route('admin.whatsapp.index'))->assertRedirect(route('login'));
    }

    private function configuredSetting(array $overrides = []): WhatsAppSetting
    {
        return WhatsAppSetting::create(array_merge([
            'api_url' => 'https://wppconnect.example.test',
            'session_name' => 'alugapro',
            'secret_key' => 'server-secret',
            'api_token' => 'stored-jwt',
            'connection_status' => 'connected',
            'connected_phone' => '5581987656944',
        ], $overrides));
    }
}

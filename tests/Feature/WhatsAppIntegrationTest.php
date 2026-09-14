<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppSetting;
use App\Services\MetaWhatsAppClient;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_encrypted_meta_cloud_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'graph_api_version' => 'v26.0',
            'phone_number_id' => '123456789012345',
            'business_account_id' => '987654321098765',
            'access_token' => 'secret-access-token',
            'app_secret' => 'secret-app-key',
            'webhook_verify_token' => 'secret-webhook-token',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('v26.0', $setting->graph_api_version);
        $this->assertSame('123456789012345', $setting->phone_number_id);
        $this->assertSame('987654321098765', $setting->business_account_id);
        $this->assertSame('secret-access-token', $setting->access_token);
        $this->assertSame('secret-app-key', $setting->app_secret);
        $this->assertSame('secret-webhook-token', $setting->webhook_verify_token);
        $this->assertNotSame('secret-access-token', DB::table('whatsapp_settings')->value('access_token'));
        $this->assertNotSame('secret-app-key', DB::table('whatsapp_settings')->value('app_secret'));

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('WhatsApp Cloud API oficial da Meta')
            ->assertSee('Validar integração e assinar webhook')
            ->assertDontSee('secret-access-token')
            ->assertDontSee('WPPConnect');
    }

    public function test_admin_can_update_messages_and_meta_template_mappings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->configuredSetting();
        $messages = [
            WhatsAppAutomation::DUE_IN_5_DAYS => 'Mensagem cinco dias para {{cliente}}.',
            WhatsAppAutomation::DUE_TODAY => 'Mensagem do vencimento de {{valor}}.',
            WhatsAppAutomation::OVERDUE => 'Mensagem de atraso há {{dias_atraso}} dias.',
            WhatsAppAutomation::GROUP_DUE_TODAY => 'Mensagem para {{grupo}}.',
        ];

        $this->actingAs($admin)
            ->put(route('admin.whatsapp.automations.update'), [
                'messages' => $messages,
                'templates' => [
                    WhatsAppAutomation::DUE_TODAY => [
                        'name' => 'alugapro_vencimento_hoje',
                        'language' => 'pt_BR',
                    ],
                    'client_access_otp' => [
                        'name' => 'alugapro_codigo_acesso',
                        'language' => 'pt_BR',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach ($messages as $key => $message) {
            $this->assertDatabaseHas('whatsapp_automations', compact('key', 'message'));
        }
        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('alugapro_vencimento_hoje', $setting->templateFor(WhatsAppAutomation::DUE_TODAY)['name']);
        $this->assertSame('alugapro_codigo_acesso', $setting->templateFor('client_access_otp')['name']);
    }

    public function test_admin_can_save_sending_credentials_before_enabling_webhooks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'graph_api_version' => 'v26.0',
            'phone_number_id' => '123456789012345',
            'business_account_id' => '987654321098765',
            'access_token' => 'temporary-access-token',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = WhatsAppSetting::query()->sole();
        $this->assertTrue($setting->isConfigured());
        $this->assertFalse($setting->hasWebhookSecurity());
    }

    public function test_phone_normalization_adds_brazilian_country_prefix_only_when_needed(): void
    {
        $client = app(MetaWhatsAppClient::class);

        $this->assertSame('5581987656944', $client->normalizePhone('81987656944'));
        $this->assertSame('5581987656944', $client->normalizePhone('+55 (81) 98765-6944'));
        $this->assertSame('15551975881', $client->normalizePhone('+1 555 197 5881'));
    }

    public function test_validation_checks_phone_and_waba_then_subscribes_webhooks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->configuredSetting(['connection_status' => 'configured']);

        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345?*' => Http::response([
                'id' => '123456789012345',
                'display_phone_number' => '+1 555-111-2222',
                'verified_name' => 'AlugaPro',
                'quality_rating' => 'GREEN',
            ]),
            'https://graph.facebook.com/v26.0/987654321098765?*' => Http::response([
                'id' => '987654321098765',
                'name' => 'AlugaPro WABA',
            ]),
            'https://graph.facebook.com/v26.0/987654321098765/subscribed_apps' => Http::response(['success' => true]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.whatsapp.verify'))
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('phone', '+1 555-111-2222')
            ->assertJsonPath('webhook_subscribed', true);

        $setting = WhatsAppSetting::query()->sole();
        $this->assertSame('connected', $setting->connection_status);
        $this->assertNotNull($setting->last_connected_at);
        $this->assertNotNull($setting->webhook_subscribed_at);

        Http::assertSentCount(3);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.facebook.com/v26.0/987654321098765/subscribed_apps'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer stored-access-token')
        );
    }

    public function test_environment_defaults_can_supply_meta_credentials(): void
    {
        config()->set('services.meta_whatsapp.graph_api_version', 'v25.0');
        config()->set('services.meta_whatsapp.phone_number_id', '111111111111111');
        config()->set('services.meta_whatsapp.business_account_id', '222222222222222');
        config()->set('services.meta_whatsapp.access_token', 'environment-access-token');
        config()->set('services.meta_whatsapp.app_secret', 'environment-app-secret');
        config()->set('services.meta_whatsapp.webhook_verify_token', 'environment-verify-token');

        $setting = WhatsAppSetting::current();

        $this->assertFalse($setting->exists);
        $this->assertTrue($setting->isConfigured());
        $this->assertTrue($setting->hasWebhookSecurity());
        $this->assertSame('v25.0', $setting->graph_api_version);
        $this->assertSame('environment-access-token', $setting->access_token);
    }

    public function test_whatsapp_service_sends_text_using_cloud_api_contract(): void
    {
        $this->configuredSetting();
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['wa_id' => '5581987656944']],
                'messages' => [['id' => 'wamid.message-123']],
            ]),
        ]);

        $log = app(WhatsAppService::class)->send(
            '+55 (81) 98765-6944',
            'Mensagem de cobrança',
            'due_today',
            'client',
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.message-123', $log->provider_reference);
        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://graph.facebook.com/v26.0/123456789012345/messages'
                && $request->hasHeader('Authorization', 'Bearer stored-access-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '5581987656944'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Mensagem de cobrança';
        });
    }

    public function test_official_test_template_uses_meta_template_payload(): void
    {
        $this->configuredSetting();
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345/messages' => Http::response([
                'messages' => [['id' => 'wamid.template-123']],
            ]),
        ]);

        $log = app(WhatsAppService::class)->sendTemplate(
            '+55 81 98765-6944',
            'hello_world',
            'en_US',
            [],
            'Modelo Meta: hello_world',
            'admin_test_template',
            'test',
        );

        $this->assertSame('sent', $log->status);
        Http::assertSent(fn (ClientRequest $request): bool => $request['type'] === 'template'
            && $request['template']['name'] === 'hello_world'
            && $request['template']['language']['code'] === 'en_US'
            && ! isset($request['template']['components'])
        );
    }

    public function test_configured_automation_uses_approved_template_with_ordered_parameters(): void
    {
        $this->configuredSetting([
            'message_templates' => [
                WhatsAppAutomation::DUE_TODAY => [
                    'name' => 'alugapro_vencimento_hoje',
                    'language' => 'pt_BR',
                ],
            ],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345/messages' => Http::response([
                'messages' => [['id' => 'wamid.automation-template']],
            ]),
        ]);

        $log = app(WhatsAppService::class)->send(
            '+55 81 98765-6944',
            'Prévia da cobrança',
            WhatsAppAutomation::DUE_TODAY,
            'client',
            null,
            ['Cliente Teste', 'R$ 100,00', '02/09/2026', 'Apartamento 1'],
        );

        $this->assertSame('sent', $log->status);
        Http::assertSent(fn (ClientRequest $request): bool => $request['type'] === 'template'
            && $request['template']['name'] === 'alugapro_vencimento_hoje'
            && $request['template']['language']['code'] === 'pt_BR'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'Cliente Teste'
            && $request['template']['components'][0]['parameters'][3]['text'] === 'Apartamento 1'
        );
    }

    public function test_whatsapp_service_uploads_then_sends_an_image(): void
    {
        $this->configuredSetting();
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345/media' => Http::response(['id' => 'media-456']),
            'https://graph.facebook.com/v26.0/123456789012345/messages' => Http::response([
                'messages' => [['id' => 'wamid.image-456']],
            ]),
        ]);

        $log = app(WhatsAppService::class)->sendImage(
            '+5581987656944',
            'image-binary-content',
            'vistoria.png',
            'Foto da vistoria',
            'inspection_image',
            'client',
            null,
            'image/png',
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.image-456', $log->provider_reference);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.facebook.com/v26.0/123456789012345/media'
            && str_contains((string) $request->header('Content-Type')[0], 'multipart/form-data')
        );
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.facebook.com/v26.0/123456789012345/messages'
            && $request['type'] === 'image'
            && $request['image']['id'] === 'media-456'
            && $request['image']['caption'] === 'Foto da vistoria'
        );
    }

    public function test_meta_webhook_verification_and_delivery_status_are_authenticated(): void
    {
        $setting = $this->configuredSetting();
        $log = NotificationLog::create([
            'recipient' => '5581987656944',
            'recipient_type' => 'client',
            'event' => 'due_today',
            'message' => 'Cobrança',
            'provider_reference' => 'wamid.message-123',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->get(route('webhooks.meta.whatsapp.verify', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'stored-webhook-token',
            'hub.challenge' => 'challenge-123',
        ]))->assertOk()->assertSeeText('challenge-123');

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $setting->phone_number_id],
                        'statuses' => [[
                            'id' => 'wamid.message-123',
                            'status' => 'delivered',
                            'timestamp' => '1788350400',
                        ]],
                    ],
                ]],
            ]],
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $json, $setting->app_secret);

        $this->call('POST', route('webhooks.meta.whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $json)->assertOk()->assertSeeText('EVENT_RECEIVED');

        $log->refresh();
        $this->assertSame('delivered', $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    public function test_meta_webhook_rejects_an_invalid_signature(): void
    {
        $this->configuredSetting();

        $this->call('POST', route('webhooks.meta.whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], '{}')->assertUnauthorized();
    }

    public function test_whatsapp_configuration_requires_admin_authentication(): void
    {
        $this->get(route('admin.whatsapp.index'))->assertRedirect(route('login'));
    }

    private function configuredSetting(array $overrides = []): WhatsAppSetting
    {
        return WhatsAppSetting::create(array_merge([
            'graph_api_version' => 'v26.0',
            'phone_number_id' => '123456789012345',
            'business_account_id' => '987654321098765',
            'access_token' => 'stored-access-token',
            'app_secret' => 'stored-app-secret',
            'webhook_verify_token' => 'stored-webhook-token',
            'connection_status' => 'connected',
            'connected_phone' => '+1 555-111-2222',
        ], $overrides));
    }
}

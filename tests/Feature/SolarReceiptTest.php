<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\SolarConfig;
use App\Models\SolarReading;
use App\Models\User;
use App\Models\WhatsAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolarReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_open_a_solar_receipt_with_the_previous_and_current_readings(): void
    {
        [$admin, $charge] = $this->solarFixture();

        $this->actingAs($admin)
            ->get(route('admin.charges.solar-receipt', $charge))
            ->assertOk()
            ->assertSee('Extrato de energia solar')
            ->assertSee('Leitura anterior')
            ->assertSee('400,000 kWh')
            ->assertSee('01/08/2026')
            ->assertSee('Leitura atual')
            ->assertSee('517,000 kWh')
            ->assertSee('03/09/2026')
            ->assertSee('117,000 kWh')
            ->assertSee('R$ 0,9500')
            ->assertSee('R$ 111,15')
            ->assertSee('Enviar extrato pelo WhatsApp');
    }

    public function test_admin_can_send_the_solar_receipt_photo_and_summary_by_whatsapp(): void
    {
        [$admin, $charge] = $this->solarFixture();
        WhatsAppSetting::create([
            'graph_api_version' => 'v26.0',
            'phone_number_id' => '123456789012345',
            'business_account_id' => '987654321098765',
            'access_token' => 'stored-access-token',
            'connection_status' => 'connected',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v26.0/123456789012345/media' => Http::response(['id' => 'solar-media-456']),
            'https://graph.facebook.com/v26.0/123456789012345/messages' => Http::response([
                'messages' => [['id' => 'solar-receipt-456']],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.charges.solar-receipt.whatsapp', $charge))
            ->assertRedirect()
            ->assertSessionHas('success', 'Extrato de energia solar enviado por WhatsApp.');

        $this->assertDatabaseHas('notification_logs', [
            'charge_id' => $charge->id,
            'lease_id' => $charge->lease_id,
            'event' => 'solar_receipt',
            'recipient_type' => 'client',
            'status' => 'sent',
            'provider_reference' => 'solar-receipt-456',
        ]);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.facebook.com/v26.0/123456789012345/messages'
            && $request['type'] === 'image'
            && $request['image']['id'] === 'solar-media-456'
            && str_contains($request['image']['caption'], '117,000 kWh')
            && str_contains($request['image']['caption'], 'R$ 111,15'));
    }

    /** @return array{0: User, 1: Charge} */
    private function solarFixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = PropertyGroup::create([
            'name' => 'Grupo Solar',
            'responsible_name' => 'Responsável Solar',
            'phone' => '81999990000',
            'pix_key' => 'pix-grupo-solar',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato solar',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $client = Client::create([
            'group_id' => $group->id,
            'name' => 'Cliente Solar',
            'phone' => '81988880000',
            'cpf' => '12345678900',
            'email' => 'cliente-solar@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Apartamento Solar',
            'slug' => 'apartamento-solar',
            'description' => 'Imóvel para teste do extrato solar.',
            'type' => 'residential',
            'street' => 'Rua Solar',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1600,
            'status' => 'rented',
            'has_solar_energy' => true,
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1600,
            'status' => 'active',
            'has_solar_energy' => true,
        ]);
        $solar = SolarConfig::create([
            'lease_id' => $lease->id,
            'initial_reading' => 300,
            'price_per_kwh' => 0.95,
        ]);
        Carbon::setTestNow('2026-08-01 10:00:00');
        SolarReading::create([
            'solar_config_id' => $solar->id,
            'reference_month' => '2026-08-01',
            'previous_reading' => 300,
            'meter_reading' => 400,
            'consumption_kwh' => 100,
            'amount' => 95,
            'ocr_status' => 'manual',
        ]);
        Carbon::setTestNow('2026-09-03 10:00:00');
        $charge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'solar',
            'generation_key' => 'solar:2026-09',
            'reference_month' => '2026-09-01',
            'due_date' => '2026-09-10',
            'amount' => 111.15,
            'status' => 'open',
        ]);
        SolarReading::create([
            'solar_config_id' => $solar->id,
            'charge_id' => $charge->id,
            'reference_month' => '2026-09-01',
            'previous_reading' => 400,
            'meter_reading' => 517,
            'consumption_kwh' => 117,
            'amount' => 111.15,
            'photo_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'photo_mime_type' => 'image/png',
            'ocr_status' => 'confirmed',
        ]);

        return [$admin, $charge];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OverdueWhatsAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_send_an_overdue_notice_from_the_charge_calendar_and_see_it_in_the_lease_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 09:00:00', 'America/Sao_Paulo'));
        [$admin, $lease, $charge] = $this->overdueCharge('2026-08-20');
        $this->configuredWhatsApp();
        WhatsAppAutomation::query()->where('key', WhatsAppAutomation::OVERDUE)->update([
            'message' => 'ATRASO | {{cliente}} | {{dias_atraso}} dias | {{valor_atualizado}} | {{imovel}}',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://wppconnect.example.test/api/alugapro/send-message' => Http::response([
                'status' => 'success',
                'response' => ['id' => ['_serialized' => 'manual-overdue-message']],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.charges.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('Enviar cobrança de atraso')
            ->assertSee(route('admin.charges.overdue-notice', $charge), false);

        $this->actingAs($admin)
            ->post(route('admin.charges.overdue-notice', $charge))
            ->assertRedirect()
            ->assertSessionHas('success', 'Cobrança de atraso enviada por WhatsApp.');

        $this->assertDatabaseHas('notification_logs', [
            'lease_id' => $lease->id,
            'charge_id' => $charge->id,
            'event' => WhatsAppAutomation::OVERDUE,
            'recipient_type' => 'client',
            'message' => 'ATRASO | Cliente em atraso | 6 dias | R$ 919,80 | Imóvel em atraso',
            'status' => 'sent',
            'provider_reference' => 'manual-overdue-message',
        ]);

        Http::assertSent(fn (ClientRequest $request): bool => $request['phone'] === '5581988880000'
            && $request['message'] === 'ATRASO | Cliente em atraso | 6 dias | R$ 919,80 | Imóvel em atraso');

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Histórico de mensagens WhatsApp')
            ->assertSee('Cobrança de atraso')
            ->assertSee('ATRASO | Cliente em atraso | 6 dias | R$ 919,80 | Imóvel em atraso')
            ->assertSee('Enviada');
    }

    public function test_automatic_overdue_notice_runs_every_three_days_until_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 09:00:00', 'America/Sao_Paulo'));
        [, , $charge] = $this->overdueCharge('2026-08-23');
        $this->configuredWhatsApp();

        Http::preventStrayRequests();
        Http::fake([
            'https://wppconnect.example.test/api/alugapro/send-message' => Http::response([
                'status' => 'success',
                'response' => ['id' => ['_serialized' => 'automatic-overdue-message']],
            ]),
        ]);

        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseHas('notification_logs', [
            'charge_id' => $charge->id,
            'event' => WhatsAppAutomation::OVERDUE,
            'status' => 'sent',
        ]);

        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseCount('notification_logs', 1);

        Carbon::setTestNow(Carbon::parse('2026-08-27 09:00:00', 'America/Sao_Paulo'));
        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseCount('notification_logs', 1);

        Carbon::setTestNow(Carbon::parse('2026-08-29 09:00:00', 'America/Sao_Paulo'));
        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseCount('notification_logs', 2);

        $charge->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => 'manual']);
        Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00', 'America/Sao_Paulo'));
        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseCount('notification_logs', 2);
        Http::assertSentCount(2);
    }

    public function test_overdue_notice_rejects_a_charge_that_is_not_overdue_and_hides_the_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 09:00:00', 'America/Sao_Paulo'));
        [$admin, , $charge] = $this->overdueCharge('2026-08-26');

        $this->actingAs($admin)
            ->get(route('admin.charges.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSee('Enviar cobrança de atraso');

        $this->actingAs($admin)
            ->post(route('admin.charges.overdue-notice', $charge))
            ->assertStatus(422);

        $this->assertDatabaseCount('notification_logs', 0);
    }

    private function overdueCharge(string $dueDate): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo em atraso',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-grupo-atraso',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato residencial',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $client = Client::create([
            'name' => 'Cliente em atraso',
            'phone' => '81988880000',
            'cpf' => '123.456.789-00',
            'email' => 'atraso@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Imóvel em atraso',
            'slug' => 'imovel-em-atraso',
            'description' => 'Imóvel usado no teste de atraso.',
            'type' => 'residential',
            'street' => 'Rua do Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 900,
            'status' => 'rented',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 20,
            'rent_amount' => 900,
            'status' => 'active',
        ]);
        $charge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'rent',
            'reference_month' => '2026-08-01',
            'due_date' => $dueDate,
            'amount' => 900,
            'status' => 'open',
            'description' => 'Aluguel de agosto',
        ]);

        return [$admin, $lease, $charge];
    }

    private function configuredWhatsApp(): void
    {
        WhatsAppSetting::create([
            'api_url' => 'https://wppconnect.example.test',
            'session_name' => 'alugapro',
            'secret_key' => 'server-secret',
            'api_token' => 'stored-jwt',
            'connection_status' => 'connected',
        ]);
    }
}

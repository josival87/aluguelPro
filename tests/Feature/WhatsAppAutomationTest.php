<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_billing_reminder_uses_editable_messages_and_brazilian_prefix_for_client_and_group(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00', 'America/Sao_Paulo'));
        [$todayCharge, $futureCharge] = $this->charges();

        WhatsAppSetting::create([
            'api_url' => 'https://wppconnect.example.test',
            'session_name' => 'alugapro',
            'secret_key' => 'server-secret',
            'api_token' => 'stored-jwt',
            'connection_status' => 'connected',
        ]);

        $messages = [
            WhatsAppAutomation::DUE_IN_5_DAYS => 'FUTURA | {{cliente}} | {{valor}} | {{vencimento}} | {{imovel}}',
            WhatsAppAutomation::DUE_TODAY => 'HOJE | {{cliente}} | {{valor}} | {{vencimento}} | {{descricao}}',
            WhatsAppAutomation::GROUP_DUE_TODAY => 'GRUPO | {{grupo}} | {{imovel}} | {{cliente}} | {{valor}}',
        ];

        foreach ($messages as $key => $message) {
            WhatsAppAutomation::query()->updateOrCreate(compact('key'), compact('message'));
        }

        Http::preventStrayRequests();
        Http::fake([
            'https://wppconnect.example.test/api/alugapro/send-message' => Http::response([
                'status' => 'success',
                'response' => ['id' => ['_serialized' => 'automatic-message']],
            ]),
        ]);

        $this->assertSame('2026-08-25', today()->toDateString());
        $this->assertSame(2, Charge::query()
            ->where(function ($query): void {
                $query->whereDate('due_date', today())
                    ->orWhereDate('due_date', today()->addDays(5));
            })->count());

        $this->artisan('billing:remind')->assertSuccessful();

        $this->assertDatabaseHas('notification_logs', [
            'charge_id' => $todayCharge->id,
            'event' => WhatsAppAutomation::DUE_TODAY,
            'message' => 'HOJE | Locatário Teste | R$ 1.200,00 | 25/08/2026 | Aluguel de agosto',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'charge_id' => $todayCharge->id,
            'event' => WhatsAppAutomation::GROUP_DUE_TODAY,
            'message' => 'GRUPO | Grupo Centro | Apartamento 101 | Locatário Teste | R$ 1.200,00',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'charge_id' => $futureCharge->id,
            'event' => WhatsAppAutomation::DUE_IN_5_DAYS,
            'message' => 'FUTURA | Locatário Teste | R$ 250,50 | 30/08/2026 | Apartamento 101',
            'status' => 'sent',
        ]);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request['phone'] === '5581988888888'
                && str_starts_with($request['message'], 'HOJE |');
        });
        Http::assertSent(function (ClientRequest $request): bool {
            return $request['phone'] === '5581999999999'
                && str_starts_with($request['message'], 'GRUPO |');
        });
        Http::assertSent(function (ClientRequest $request): bool {
            return $request['phone'] === '5581988888888'
                && str_starts_with($request['message'], 'FUTURA |');
        });

        $this->artisan('billing:remind')->assertSuccessful();
        $this->assertDatabaseCount('notification_logs', 3);
        Http::assertSentCount(3);
    }

    public function test_automatic_messages_run_daily_in_the_billing_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'billing:remind'));

        $this->assertNotNull($event);
        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function charges(): array
    {
        $group = PropertyGroup::create([
            'name' => 'Grupo Centro',
            'responsible_name' => 'Responsável Teste',
            'phone' => '81999999999',
            'pix_key' => 'pix-grupo-centro',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Apartamento 101',
            'slug' => 'apartamento-101-automacao',
            'description' => 'Imóvel de teste',
            'type' => 'residential',
            'street' => 'Rua de Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1200,
            'status' => 'rented',
        ]);
        $client = Client::create([
            'name' => 'Locatário Teste',
            'phone' => '81988888888',
            'cpf' => '12345678901',
            'email' => 'locatario@example.test',
            'status' => 'active',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'due_day' => 25,
            'rent_amount' => 1200,
            'status' => 'active',
        ]);

        $todayCharge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'rent',
            'reference_month' => '2026-08-01',
            'due_date' => '2026-08-25',
            'amount' => 1200,
            'status' => 'open',
            'description' => 'Aluguel de agosto',
        ]);
        $futureCharge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'solar',
            'reference_month' => '2026-08-01',
            'due_date' => '2026-08-30',
            'amount' => 250.50,
            'status' => 'open',
            'description' => 'Energia solar de agosto',
        ]);

        return [$todayCharge, $futureCharge];
    }
}

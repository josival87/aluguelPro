<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyChargeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_generates_an_open_charge_for_an_active_lease_even_after_its_end_date(): void
    {
        $lease = $this->lease([
            'end_date' => '2025-12-31',
            'due_day' => 10,
            'rent_amount' => 1450.75,
            'status' => 'active',
        ]);

        $created = app(BillingService::class)->generateMonth(Carbon::parse('2026-09-18'));

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('charges', [
            'lease_id' => $lease->id,
            'client_id' => $lease->client_id,
            'type' => 'rent',
            'reference_month' => '2026-09-01 00:00:00',
            'due_date' => '2026-09-10 00:00:00',
            'amount' => 1450.75,
            'status' => 'open',
        ]);
    }

    public function test_it_only_creates_missing_charges_and_never_changes_an_existing_one(): void
    {
        $existingLease = $this->lease();
        $missingLease = $this->lease();
        $existing = Charge::create([
            'lease_id' => $existingLease->id,
            'client_id' => $existingLease->client_id,
            'type' => 'rent',
            'generation_key' => 'rent:2026-09',
            'reference_month' => '2026-09-01 00:00:00',
            'due_date' => '2026-09-10',
            'amount' => 1200,
            'status' => 'paid',
            'paid_at' => '2026-09-08 12:00:00',
            'payment_method' => 'manual',
        ]);

        $billing = app(BillingService::class);
        $this->assertSame(1, $billing->generateMonth(Carbon::parse('2026-09-01')));
        $this->assertSame(0, $billing->generateMonth(Carbon::parse('2026-09-01')));

        $this->assertSame(2, Charge::where('type', 'rent')->count());
        $this->assertSame('paid', $existing->fresh()->status);
        $this->assertSame('manual', $existing->fresh()->payment_method);
        $this->assertDatabaseHas('charges', [
            'lease_id' => $missingLease->id,
            'reference_month' => '2026-09-01 00:00:00',
            'status' => 'open',
        ]);
    }

    public function test_manual_button_generates_only_missing_charges_for_the_selected_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lease = $this->lease(['end_date' => '2025-12-31']);

        $this->actingAs($admin)
            ->post(route('admin.charges.generate'), ['month' => '2026-09'])
            ->assertRedirect()
            ->assertSessionHas('success', '1 cobrança(s) criada(s).');

        $this->actingAs($admin)
            ->post(route('admin.charges.generate'), ['month' => '2026-09'])
            ->assertRedirect()
            ->assertSessionHas('success', '0 cobrança(s) criada(s).');

        $this->assertSame(1, $lease->charges()->where('type', 'rent')->count());
    }

    public function test_closed_and_not_yet_started_leases_are_not_charged(): void
    {
        $closed = $this->lease(['status' => 'closed']);
        $future = $this->lease(['start_date' => '2026-10-01']);

        $this->assertSame(0, app(BillingService::class)->generateMonth(Carbon::parse('2026-09-01')));
        $this->assertFalse($closed->charges()->exists());
        $this->assertFalse($future->charges()->exists());
    }

    public function test_next_option_generates_the_following_month_in_the_billing_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 23:55:00', 'America/Sao_Paulo'));
        $lease = $this->lease(['end_date' => '2025-12-31']);

        $this->artisan('billing:generate', ['--next' => true])
            ->expectsOutput('1 cobrança(s) criada(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('charges', [
            'lease_id' => $lease->id,
            'reference_month' => '2026-09-01 00:00:00',
            'status' => 'open',
        ]);
    }

    public function test_monthly_schedule_runs_at_the_end_of_the_month_for_the_following_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 23:55:00', 'America/Sao_Paulo'));
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'billing:generate --next'));

        $this->assertNotNull($event);
        $this->assertSame('55 23 * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->isDue($this->app));
        $this->assertTrue($event->filtersPass($this->app));

        Carbon::setTestNow(Carbon::parse('2026-08-30 23:55:00', 'America/Sao_Paulo'));
        $this->assertFalse($event->filtersPass($this->app));
    }

    private function lease(array $overrides = []): Lease
    {
        $suffix = uniqid();
        $group = PropertyGroup::create([
            'name' => 'Grupo '.$suffix,
            'responsible_name' => 'Responsável',
            'phone' => '81999999999',
            'pix_key' => 'pix-'.$suffix,
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Imóvel '.$suffix,
            'slug' => 'imovel-'.$suffix,
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
            'name' => 'Locatário '.$suffix,
            'phone' => '81988888888',
            'cpf' => sprintf('%011u', crc32($suffix)),
            'email' => $suffix.'@example.test',
            'status' => 'active',
        ]);

        return Lease::create(array_merge([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2025-01-01',
            'end_date' => '2026-12-31',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1200,
            'status' => 'active',
        ], $overrides));
    }
}

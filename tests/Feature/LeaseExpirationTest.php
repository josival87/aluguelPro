<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeaseExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business.billing_timezone' => 'America/Sao_Paulo']);
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    }

    public static function expirationCases(): array
    {
        return [
            'future months' => ['2026-12-15', 'Vence em 3 meses', false],
            'future month' => ['2026-10-15', 'Vence em 1 mês', false],
            'tomorrow' => ['2026-09-16', 'Vence em menos de 1 mês', false],
            'today' => ['2026-09-15', 'Vence hoje', false],
            'yesterday' => ['2026-09-14', 'Vencido há menos de 1 mês', true],
            'past month' => ['2026-08-15', 'Vencido há 1 mês', true],
            'past months' => ['2026-06-15', 'Vencido há 3 meses', true],
            'partial month' => ['2026-07-31', 'Vencido há 1 mês', true],
            'over a year' => ['2025-08-15', 'Vencido há 13 meses', true],
        ];
    }

    #[DataProvider('expirationCases')]
    public function test_period_shows_calendar_months_and_the_correct_color(string $endDate, string $label, bool $expired): void
    {
        $lease = $this->lease(['end_date' => $endDate]);
        $this->assertSame(['label' => $label, 'expired' => $expired], $lease->contractExpiration());

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.leases.index'))
            ->assertOk()
            ->assertSee('até '.Carbon::parse($endDate)->format('d/m/Y'))
            ->assertSee('color:var(--'.($expired ? 'red' : 'blue').')">'.$label.'</small>', false);
    }

    public function test_missing_end_date_has_no_expiration_label(): void
    {
        $lease = $this->lease(['end_date' => null]);
        $this->assertNull($lease->contractExpiration());
        $this->assertSame('active', $lease->status);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.leases.index'))
            ->assertOk()
            ->assertSee('até a definir')
            ->assertDontSee('Vence em')
            ->assertDontSee('Vencido há');
    }

    public function test_filters_refresh_newly_expired_leases_and_preserve_search_and_pagination(): void
    {
        $expired = $this->lease(['end_date' => '2026-08-15']);
        DB::table('leases')->where('id', $expired->id)->update(['status' => 'active']);
        $active = $this->lease();
        $closed = $this->lease(['end_date' => '2026-08-15', 'status' => 'closed']);
        for ($i = 0; $i < 15; $i++) {
            Lease::create([
                ...$expired->only(['property_id', 'client_id', 'start_date', 'end_date', 'contract_months', 'due_day', 'rent_amount']),
                'status' => 'active',
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)
            ->get(route('admin.leases.index', ['status' => 'active_expired', 'q' => $expired->client->name]))
            ->assertOk()
            ->assertSee('Ativo - vencido')
            ->assertSee('Vencido há 1 mês')
            ->assertDontSee($active->client->name)
            ->assertDontSee($closed->client->name)
            ->assertViewHas('leases', fn ($leases) => $leases->total() === 16);
        $this->assertStringContainsString('status=active_expired', $response->viewData('leases')->url(2));
        $this->assertStringContainsString('q=', $response->viewData('leases')->url(2));
        $this->assertSame('active_expired', $expired->fresh()->status);

        $this->get(route('admin.leases.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee($active->client->name)
            ->assertDontSee($expired->client->name)
            ->assertViewHas('leases', fn ($leases) => $leases->total() === 1);
    }

    public function test_migration_changes_only_active_leases_before_the_local_date_and_can_be_reversed(): void
    {
        // It is already September 16 in UTC, but still September 15 in São Paulo.
        $this->travelTo(Carbon::parse('2026-09-16 01:00:00', 'UTC'));
        $cases = [
            ['active', '2026-09-14', 'active_expired'],
            ['active', '2026-09-15', 'active'],
            ['active', '2026-09-16', 'active'],
            ['active', null, 'active'],
            ['closed', '2026-09-14', 'closed'],
            ['cancelled', '2026-09-14', 'cancelled'],
            ['awaiting_completion', '2026-09-14', 'awaiting_completion'],
            ['awaiting_signatures', '2026-09-14', 'awaiting_signatures'],
            ['active_expired', '2026-09-14', 'active_expired'],
        ];
        $leases = [];
        foreach ($cases as [$status, $endDate, $expected]) {
            $lease = $this->lease(['status' => $status, 'end_date' => $endDate]);
            DB::table('leases')->where('id', $lease->id)->update(['status' => $status]);
            $leases[] = [$lease, $expected];
        }
        $migration = require database_path('migrations/2026_09_15_000100_mark_expired_active_leases.php');
        $migration->up();
        $migration->up();
        foreach ($leases as [$lease, $expected]) {
            $this->assertSame($expected, $lease->fresh()->status);
        }
        $this->assertSame('Vence hoje', $leases[1][0]->contractExpiration()['label']);

        $migration->down();
        $this->assertSame(0, DB::table('leases')->where('status', 'active_expired')->count());
        $this->assertSame('closed', $leases[4][0]->fresh()->status);
    }

    public function test_expired_status_is_accepted_keeps_property_rented_and_renewal_restores_active(): void
    {
        $lease = $this->lease(['status' => 'awaiting_completion', 'end_date' => '2026-08-15']);
        $data = $lease->only(['property_id', 'client_id', 'start_date', 'end_date', 'contract_months', 'due_day', 'rent_amount']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.leases.update', $lease), [...$data, 'status' => 'active_expired'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.leases.show', $lease));
        $this->assertSame('active_expired', $lease->fresh()->status);
        $this->assertTrue($lease->fresh()->isInForce());
        $this->assertSame('rented', $lease->property->fresh()->status);
        $this->assertSame(1, app(BillingService::class)->generateMonth(Carbon::parse('2026-09-01')));

        $this->get(route('admin.leases.edit', $lease))->assertOk()->assertSee('Ativo - vencido');
        $this->put(route('admin.leases.update', $lease), [...$data, 'status' => 'active_expired', 'end_date' => '2027-08-15'])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $lease->fresh()->status);

        $this->put(route('admin.leases.update', $lease), [...$data, 'status' => 'closed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $lease->fresh()->status);
        $this->assertSame('available', $lease->property->fresh()->status);
    }

    public function test_list_refresh_respects_the_admin_group_scope(): void
    {
        $mine = $this->lease(['end_date' => '2026-09-14']);
        $other = $this->lease(['end_date' => '2026-09-14']);
        $otherClientName = $other->client->name;
        DB::table('leases')->update(['status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'group_id' => $mine->property->group_id]);

        $this->actingAs($admin)->get(route('admin.leases.index', ['status' => 'active_expired']))
            ->assertOk()
            ->assertSee($mine->client->name)
            ->assertDontSee($otherClientName);
        $this->assertDatabaseHas('leases', ['id' => $mine->id, 'status' => 'active_expired']);
        $this->assertDatabaseHas('leases', ['id' => $other->id, 'status' => 'active']);
    }

    public function test_daily_command_marks_new_expirations_without_waiting_for_a_page_visit(): void
    {
        $lease = $this->lease(['end_date' => '2026-09-15']);
        $this->assertSame('active', $lease->status);
        $this->travelTo(Carbon::parse('2026-09-16 00:05:00', 'America/Sao_Paulo'));
        $this->artisan('leases:mark-expired')->expectsOutput('1 aluguel(is) atualizado(s).')->assertSuccessful();
        $this->artisan('leases:mark-expired')->expectsOutput('0 aluguel(is) atualizado(s).')->assertSuccessful();
        $this->assertSame('active_expired', $lease->fresh()->status);
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'leases:mark-expired'));
        $this->assertNotNull($event);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
        $this->assertTrue($event->isDue($this->app));
    }

    private function lease(array $overrides = []): Lease
    {
        $suffix = uniqid();
        $group = PropertyGroup::create([
            'name' => 'Grupo '.$suffix, 'responsible_name' => 'Responsável',
            'phone' => '81999999999', 'pix_key' => 'pix-'.$suffix,
        ]);
        $contract = Contract::create(['title' => 'Contrato '.$suffix, 'content' => 'Contrato de teste.', 'active' => true]);
        $property = Property::create([
            'group_id' => $group->id, 'contract_id' => $contract->id,
            'title' => 'Imóvel '.$suffix, 'slug' => 'imovel-'.$suffix, 'description' => 'Imóvel de teste',
            'type' => 'residential', 'street' => 'Rua de Teste', 'neighborhood' => 'Centro',
            'city' => 'Recife', 'state' => 'PE', 'rent_amount' => 1200, 'status' => 'rented',
        ]);
        $client = Client::create([
            'name' => 'Locatário '.$suffix, 'phone' => '81988888888',
            'cpf' => sprintf('%011u', crc32($suffix)), 'status' => 'active',
        ]);

        return Lease::create([
            'property_id' => $property->id, 'client_id' => $client->id,
            'start_date' => '2025-01-01', 'end_date' => '2026-12-15',
            'contract_months' => 12, 'due_day' => 10, 'rent_amount' => 1200, 'status' => 'active',
            ...$overrides,
        ]);
    }
}

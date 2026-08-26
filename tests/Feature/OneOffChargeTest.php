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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneOffChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_open_and_paid_one_off_charges_from_the_lease_profile(): void
    {
        [$admin, $lease] = $this->lease();

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Nova cobrança avulsa')
            ->assertSee(route('admin.leases.charges.store', $lease), false)
            ->assertSee('Energia solar');

        $this->actingAs($admin)
            ->post(route('admin.leases.charges.store', $lease), [
                'type' => 'rent',
                'amount' => '275.90',
                'due_date' => '2026-09-18',
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.leases.show', $lease).'#cobrancas')
            ->assertSessionHas('success', 'Cobrança avulsa criada.');

        $openCharge = Charge::latest('id')->firstOrFail();
        $this->assertSame('rent', $openCharge->type);
        $this->assertNull($openCharge->generation_key);
        $this->assertSame('2026-09-01', $openCharge->reference_month->toDateString());
        $this->assertSame('2026-09-18', $openCharge->due_date->toDateString());
        $this->assertSame('275.90', $openCharge->amount);
        $this->assertSame('open', $openCharge->status);
        $this->assertNull($openCharge->paid_at);
        $this->assertNull($openCharge->payment_method);

        $this->actingAs($admin)
            ->post(route('admin.leases.charges.store', $lease), [
                'type' => 'solar',
                'amount' => '89.50',
                'due_date' => '2026-09-22',
                'status' => 'paid',
            ])
            ->assertRedirect(route('admin.leases.show', $lease).'#cobrancas');

        $paidCharge = Charge::latest('id')->firstOrFail();
        $this->assertSame('solar', $paidCharge->type);
        $this->assertNull($paidCharge->generation_key);
        $this->assertSame('paid', $paidCharge->status);
        $this->assertSame('manual', $paidCharge->payment_method);
        $this->assertNotNull($paidCharge->paid_at);

        $this->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Avulsa')
            ->assertSee('R$ 89,50');
    }

    public function test_one_off_charge_does_not_block_monthly_generation_or_another_one_off_charge(): void
    {
        [$admin, $lease] = $this->lease();
        $payload = [
            'type' => 'rent',
            'amount' => '125.00',
            'due_date' => '2026-09-10',
            'status' => 'open',
        ];

        $this->actingAs($admin)->post(route('admin.leases.charges.store', $lease), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.leases.charges.store', $lease), $payload)->assertRedirect();

        $this->assertSame(1, app(BillingService::class)->generateMonth(Carbon::parse('2026-09-01')));
        $this->assertSame(0, app(BillingService::class)->generateMonth(Carbon::parse('2026-09-01')));
        $this->assertSame(2, $lease->charges()->whereNull('generation_key')->count());
        $this->assertSame(1, $lease->charges()->where('generation_key', 'rent:2026-09')->count());
    }

    public function test_one_off_charge_fields_are_validated_and_clients_cannot_create_them(): void
    {
        [$admin, $lease] = $this->lease();

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $lease))
            ->post(route('admin.leases.charges.store', $lease), [
                'type' => 'fee',
                'amount' => '0',
                'due_date' => '18/09/2026',
                'status' => 'cancelled',
            ])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHasErrors(['type', 'amount', 'due_date', 'status']);

        $client = User::factory()->create(['role' => 'client', 'active' => true]);
        $this->actingAs($client)
            ->post(route('admin.leases.charges.store', $lease), [
                'type' => 'rent',
                'amount' => '100.00',
                'due_date' => '2026-09-18',
                'status' => 'open',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('charges', 0);
    }

    private function lease(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Avulsas',
            'responsible_name' => 'Responsável Financeiro',
            'phone' => '81999990000',
            'pix_key' => 'financeiro@example.test',
        ]);
        $client = Client::create([
            'name' => 'Cliente Avulsas',
            'phone' => '81988880000',
            'cpf' => '987.654.321-00',
            'email' => 'cliente-avulsas@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Apartamento Avulsas',
            'slug' => 'apartamento-avulsas',
            'description' => 'Imóvel usado no teste de cobranças avulsas.',
            'type' => 'residential',
            'street' => 'Rua das Avulsas',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 900,
            'status' => 'rented',
            'has_solar_energy' => true,
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-01-01',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 900,
            'status' => 'active',
            'has_solar_energy' => true,
        ]);

        return [$admin, $lease];
    }
}

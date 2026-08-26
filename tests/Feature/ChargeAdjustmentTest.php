<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_an_open_charge_from_the_lease_page(): void
    {
        [$admin, $lease, $charge] = $this->leaseWithOpenCharge();
        $pix = $this->activePix($charge);

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee(route('admin.charges.amount.update', $charge), false)
            ->assertSee(route('admin.charges.waive', $charge), false)
            ->assertSee('Zerar e dar baixa');

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $lease))
            ->patch(route('admin.charges.amount.update', $charge), ['amount' => '725.40'])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHas('success', 'Valor da cobrança atualizado.');

        $this->assertSame('725.40', $charge->fresh()->amount);
        $this->assertSame('open', $charge->fresh()->status);
        $this->assertSame('cancelled', $pix->fresh()->status);
        $this->assertDatabaseHas('charge_adjustments', [
            'charge_id' => $charge->id,
            'user_id' => $admin->id,
            'previous_amount' => 900,
            'new_amount' => 725.40,
            'action' => 'amount_updated',
        ]);

        $this->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Alterada de R$ 900,00 para R$ 725,40')
            ->assertSee($admin->name);
    }

    public function test_admin_can_zero_and_settle_an_open_charge(): void
    {
        [$admin, $lease, $charge] = $this->leaseWithOpenCharge();
        $pix = $this->activePix($charge);

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $lease))
            ->patch(route('admin.charges.waive', $charge))
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHas('success', 'Cobrança zerada e baixada sem recebimento.');

        $charge->refresh();
        $this->assertSame('0.00', $charge->amount);
        $this->assertSame('paid', $charge->status);
        $this->assertSame('waiver', $charge->payment_method);
        $this->assertNotNull($charge->paid_at);
        $this->assertSame('cancelled', $pix->fresh()->status);
        $this->assertDatabaseHas('charge_adjustments', [
            'charge_id' => $charge->id,
            'user_id' => $admin->id,
            'previous_amount' => 900,
            'new_amount' => 0,
            'action' => 'waived',
        ]);

        $this->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Baixada sem valor')
            ->assertSee('Zerada de R$ 900,00');
    }

    public function test_paid_charge_must_be_reopened_before_its_amount_is_changed(): void
    {
        [$admin, $lease, $charge] = $this->leaseWithOpenCharge();
        $charge->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'manual',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $lease))
            ->patch(route('admin.charges.amount.update', $charge), ['amount' => '500.00'])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHasErrors('charge');

        $this->assertSame('900.00', $charge->fresh()->amount);
        $this->assertDatabaseCount('charge_adjustments', 0);
    }

    public function test_zero_value_uses_the_dedicated_waiver_action(): void
    {
        [$admin, $lease, $charge] = $this->leaseWithOpenCharge();

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $lease))
            ->patch(route('admin.charges.amount.update', $charge), ['amount' => '0'])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHasErrors('amount');

        $this->assertSame('900.00', $charge->fresh()->amount);
        $this->assertDatabaseCount('charge_adjustments', 0);
    }

    public function test_client_cannot_change_or_waive_a_charge(): void
    {
        [, , $charge] = $this->leaseWithOpenCharge();
        $clientUser = User::factory()->create(['role' => 'client', 'active' => true]);

        $this->actingAs($clientUser)
            ->patch(route('admin.charges.amount.update', $charge), ['amount' => '500.00'])
            ->assertForbidden();

        $this->patch(route('admin.charges.waive', $charge))->assertForbidden();
        $this->assertSame('900.00', $charge->fresh()->amount);
    }

    private function leaseWithOpenCharge(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Ajustes',
            'responsible_name' => 'Responsável Financeiro',
            'phone' => '81999990000',
            'pix_key' => 'financeiro@example.test',
        ]);
        $client = Client::create([
            'name' => 'Cliente Ajustes',
            'phone' => '81988880000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente-ajustes@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Apartamento Ajustes',
            'slug' => 'apartamento-ajustes',
            'description' => 'Imóvel usado no teste de ajustes de cobrança.',
            'type' => 'residential',
            'street' => 'Rua dos Ajustes',
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
            'due_day' => 10,
            'rent_amount' => 900,
            'status' => 'active',
        ]);
        $charge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'rent',
            'reference_month' => '2026-08-01',
            'due_date' => '2026-08-10',
            'amount' => 900,
            'status' => 'open',
        ]);

        return [$admin, $lease, $charge];
    }

    private function activePix(Charge $charge)
    {
        return $charge->pixPayments()->create([
            'txid' => 'AJUSTE'.$charge->id,
            'original_amount' => 900,
            'fine_amount' => 0,
            'interest_amount' => 0,
            'total_amount' => 900,
            'br_code' => 'PIX-ANTIGO',
            'provider' => 'local_emv_static',
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeCalendarDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_chip_shows_only_property_title_and_amount_without_cents(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Centro',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-grupo-centro',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato residencial',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $client = Client::create([
            'name' => 'Cliente da cobrança',
            'phone' => '81988880000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Ebm 01',
            'slug' => 'ebm-01',
            'description' => 'Imóvel usado no teste do calendário.',
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
        Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'solar',
            'reference_month' => '2026-08-01',
            'due_date' => '2026-08-10',
            'amount' => 100,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.charges.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('Total do mês(2)')
            ->assertSee('Recebidos(1)')
            ->assertSee('Em aberto(1)')
            ->assertSee('Ebm 01 - R$900')
            ->assertSee('Dar baixa')
            ->assertSee('Ver ficha')
            ->assertSee(route('admin.charges.paid', $charge), false)
            ->assertSee(route('admin.leases.show', $lease), false)
            ->assertDontSee('⌂ Aluguel')
            ->assertDontSee('Ebm 01 - R$900,00');

        $this->actingAs($admin)
            ->patch(route('admin.charges.paid', $charge))
            ->assertRedirect();

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'paid',
            'payment_method' => 'manual',
        ]);
        $this->assertNotNull($charge->fresh()->paid_at);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGroupAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_groups_admin_can_select_and_change_a_users_group_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'group_id' => null]);
        $groupA = $this->createGroup('Grupo Alfa');
        $groupB = $this->createGroup('Grupo Beta');

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Todos os grupos')
            ->assertSee('Somente Grupo Alfa')
            ->assertSee('Somente Grupo Beta');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->userData('gestor-alfa', $groupA->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $manager = User::query()->where('login', 'gestor-alfa')->sole();
        $this->assertSame($groupA->id, $manager->group_id);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $manager), [
                ...$this->userData('gestor-alfa', null),
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull($manager->fresh()->group_id);
    }

    public function test_restricted_admin_only_sees_records_and_values_from_their_group(): void
    {
        $fixtures = $this->twoGroupFixtures();
        $admin = User::factory()->create([
            'name' => 'Administrador Alfa',
            'role' => 'admin',
            'group_id' => $fixtures['groupA']->id,
        ]);
        User::factory()->create([
            'name' => 'Gestor Beta Oculto',
            'role' => 'manager',
            'group_id' => $fixtures['groupB']->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Imóvel Alfa')
            ->assertSee('R$ 1.111,00')
            ->assertDontSee('Imóvel Beta')
            ->assertDontSee('R$ 9.999,00')
            ->assertSee('Grupo Alfa')
            ->assertDontSee('Grupo Beta');

        $this->actingAs($admin)
            ->get(route('admin.properties.index'))
            ->assertOk()
            ->assertSee('Imóvel Alfa')
            ->assertDontSee('Imóvel Beta');

        $this->actingAs($admin)
            ->get(route('admin.leases.index'))
            ->assertOk()
            ->assertSee('Cliente Alfa')
            ->assertDontSee('Cliente Beta');

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Cliente Alfa')
            ->assertDontSee('Cliente Beta');

        $this->actingAs($admin)
            ->get(route('admin.charges.index', ['month' => '2026-08', 'group' => $fixtures['groupB']->id]))
            ->assertOk()
            ->assertSee('Imóvel Alfa')
            ->assertSee('R$1.111')
            ->assertDontSee('Imóvel Beta')
            ->assertDontSee('R$9.999');

        $this->actingAs($admin)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertSee('Grupo Alfa')
            ->assertDontSee('Grupo Beta')
            ->assertDontSee('Novo grupo');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Administrador Alfa')
            ->assertDontSee('Gestor Beta Oculto');

        $this->actingAs($admin)
            ->post(route('admin.charges.generate'), ['month' => '2026-09'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('charges', [
            'lease_id' => $fixtures['leaseA']->id,
            'generation_key' => 'rent:2026-09',
        ]);
        $this->assertDatabaseMissing('charges', [
            'lease_id' => $fixtures['leaseB']->id,
            'generation_key' => 'rent:2026-09',
        ]);
    }

    public function test_restricted_admin_cannot_open_or_submit_records_from_another_group(): void
    {
        $fixtures = $this->twoGroupFixtures();
        $admin = User::factory()->create([
            'role' => 'admin',
            'group_id' => $fixtures['groupA']->id,
        ]);
        $otherAdmin = User::factory()->create([
            'role' => 'manager',
            'group_id' => $fixtures['groupB']->id,
        ]);

        $this->actingAs($admin)->get(route('admin.groups.edit', $fixtures['groupB']))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.properties.show', $fixtures['propertyB']))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.leases.show', $fixtures['leaseB']))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.clients.show', $fixtures['clientB']))->assertNotFound();
        $this->actingAs($admin)->get(route('contracts.show', $fixtures['leaseContractB']))->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.charges.paid', $fixtures['chargeB']))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.users.edit', $otherAdmin))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.groups.create'))->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->userData('novo-gestor', null))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'login' => 'novo-gestor',
            'group_id' => $fixtures['groupA']->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.properties.store'), $this->propertyData(
                $fixtures['groupB'],
                $fixtures['contract'],
                'Tentativa Cruzada',
                'tentativa-cruzada',
            ))
            ->assertRedirect(route('admin.properties.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('properties', [
            'slug' => 'tentativa-cruzada',
            'group_id' => $fixtures['groupA']->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.leases.store'), [
                'property_id' => $fixtures['propertyB']->id,
                'client_id' => $fixtures['clientA']->id,
                'contract_months' => 12,
                'due_day' => 10,
                'rent_amount' => 1000,
                'status' => 'awaiting_completion',
            ])
            ->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function twoGroupFixtures(): array
    {
        $groupA = $this->createGroup('Grupo Alfa');
        $groupB = $this->createGroup('Grupo Beta');
        $contract = Contract::create([
            'title' => 'Contrato por grupo',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $clientA = $this->createClient($groupA, 'Cliente Alfa', '11111111111');
        $clientB = $this->createClient($groupB, 'Cliente Beta', '22222222222');
        $propertyA = Property::create($this->propertyData($groupA, $contract, 'Imóvel Alfa', 'imovel-alfa'));
        $propertyB = Property::create($this->propertyData($groupB, $contract, 'Imóvel Beta', 'imovel-beta'));
        $leaseA = $this->createLease($propertyA, $clientA, 1111);
        $leaseB = $this->createLease($propertyB, $clientB, 9999);
        $chargeA = $this->createCharge($leaseA, $clientA, 1111);
        $chargeB = $this->createCharge($leaseB, $clientB, 9999);
        $leaseContractB = LeaseContract::create([
            'lease_id' => $leaseB->id,
            'template_id' => $contract->id,
            'final_content' => 'Contrato Beta.',
            'content_hash' => hash('sha256', 'Contrato Beta.'),
            'status' => 'finalized',
            'generated_at' => now(),
        ]);

        return compact(
            'groupA', 'groupB', 'contract', 'clientA', 'clientB', 'propertyA', 'propertyB',
            'leaseA', 'leaseB', 'chargeA', 'chargeB', 'leaseContractB',
        );
    }

    private function createGroup(string $name): PropertyGroup
    {
        return PropertyGroup::create([
            'name' => $name,
            'responsible_name' => 'Responsável '.$name,
            'phone' => '81999990000',
            'pix_key' => 'pix-'.strtolower(str_replace(' ', '-', $name)),
        ]);
    }

    private function createClient(PropertyGroup $group, string $name, string $cpf): Client
    {
        return Client::create([
            'group_id' => $group->id,
            'name' => $name,
            'phone' => '81988880000',
            'cpf' => $cpf,
            'rg' => '1234567',
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function propertyData(PropertyGroup $group, Contract $contract, string $title, string $slug): array
    {
        return [
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Imóvel para validar isolamento entre grupos.',
            'type' => 'residential',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'parking_spaces' => 1,
            'street' => 'Rua do Grupo',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1000,
            'status' => 'rented',
            'has_solar_energy' => false,
        ];
    }

    private function createLease(Property $property, Client $client, float $amount): Lease
    {
        return Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-01-01',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => $amount,
            'status' => 'active',
            'has_solar_energy' => false,
        ]);
    }

    private function createCharge(Lease $lease, Client $client, float $amount): Charge
    {
        return Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'rent',
            'generation_key' => 'rent:2026-08',
            'reference_month' => '2026-08-01',
            'due_date' => '2026-08-10',
            'amount' => $amount,
            'status' => 'open',
        ]);
    }

    /** @return array<string, mixed> */
    private function userData(string $login, ?int $groupId): array
    {
        return [
            'name' => 'Usuário '.$login,
            'email' => $login.'@example.test',
            'login' => $login,
            'role' => 'manager',
            'group_id' => $groupId,
            'active' => '1',
            'password' => 'Senha@2026',
            'password_confirmation' => 'Senha@2026',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_property_and_active_lease_totals_and_group_charge_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $groupA = $this->createGroup('Grupo A');
        $groupB = $this->createGroup('Grupo B');
        $contract = Contract::create(['title' => 'Contrato dashboard', 'content' => 'Conteúdo.', 'active' => true]);
        $properties = collect([
            $this->createProperty($groupA, $contract, 'Imóvel A', 'imovel-a', 'rented'),
            $this->createProperty($groupA, $contract, 'Imóvel B', 'imovel-b'),
            $this->createProperty($groupB, $contract, 'Imóvel C', 'imovel-c'),
        ]);
        $client = Client::create([
            'name' => 'Cliente Dashboard',
            'phone' => '81999990000',
            'cpf' => '123.456.789-00',
            'email' => 'dashboard@example.com',
            'status' => 'active',
        ]);

        Lease::create($this->leaseData($properties[0], $client, 'active'));
        Lease::create($this->leaseData($properties[1], $client, 'awaiting_completion'));
        Lease::create($this->leaseData($properties[2], $client, 'closed'));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Total de imóveis')
            ->assertSee('Aluguéis vigentes')
            ->assertSee('Contratos ativos no momento')
            ->assertDontSee('Novos aluguéis')
            ->assertSee(route('admin.charges.index', ['group' => $groupA->id]), false)
            ->assertSee(route('admin.charges.index', ['group' => $groupB->id]), false)
            ->assertSee('Selecione o grupo');

        $html = $response->getContent();
        $totalCard = substr($html, strpos($html, 'Total de imóveis'), 500);
        $activeCard = substr($html, strpos($html, 'Aluguéis vigentes'), 500);

        $this->assertStringContainsString('<strong>3</strong>', $totalCard);
        $this->assertStringContainsString('<strong>1</strong>', $activeCard);
    }

    private function createGroup(string $name): PropertyGroup
    {
        return PropertyGroup::create([
            'name' => $name,
            'responsible_name' => 'Responsável '.$name,
            'phone' => '81999990000',
            'pix_key' => 'pix-'.$name,
        ]);
    }

    private function createProperty(PropertyGroup $group, Contract $contract, string $title, string $slug, string $status = 'available'): Property
    {
        return Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Imóvel do dashboard.',
            'type' => 'residential',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'parking_spaces' => 1,
            'street' => 'Rua do Dashboard',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1000,
            'status' => $status,
            'has_solar_energy' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function leaseData(Property $property, Client $client, string $status): array
    {
        return [
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1000,
            'status' => $status,
            'has_solar_energy' => false,
        ];
    }
}

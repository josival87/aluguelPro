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

class LeaseEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_orders_clients_by_active_lease_count_and_shows_the_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo da ordenação',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-ordenacao',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato da ordenação',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $twoActive = $this->createClient('Aline Dois', '111.111.111-11');
        $oneActive = $this->createClient('Bruna Um', '222.222.222-22');
        $zeroActive = $this->createClient('Carla Zero', '333.333.333-33');

        foreach ([
            [$twoActive, 'active', 'imovel-ativo-um'],
            [$twoActive, 'active', 'imovel-ativo-dois'],
            [$oneActive, 'active', 'imovel-ativo-tres'],
            [$zeroActive, 'closed', 'imovel-encerrado'],
        ] as [$client, $status, $slug]) {
            $property = $this->createProperty($group, $contract, 'Imóvel '.$slug, $slug, 'rented');
            Lease::create([
                'property_id' => $property->id,
                'client_id' => $client->id,
                'contract_months' => 12,
                'due_day' => 10,
                'rent_amount' => 1500,
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.leases.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Carla Zero - 333.333.333-33 - 0 aluguéis ativos',
                'Bruna Um - 222.222.222-22 - 1 aluguel ativo',
                'Aline Dois - 111.111.111-11 - 2 aluguéis ativos',
            ]);
    }

    public function test_edit_lists_available_properties_and_the_current_rented_property(): void
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
            'name' => 'Cliente do aluguel',
            'phone' => '81988880000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente@example.test',
            'status' => 'active',
        ]);
        $currentProperty = $this->createProperty($group, $contract, 'Imóvel atual alugado', 'imovel-atual-alugado', 'rented');
        $availableProperty = $this->createProperty($group, $contract, 'Imóvel disponível', 'imovel-disponivel', 'available');
        $this->createProperty($group, $contract, 'Outro imóvel alugado', 'outro-imovel-alugado', 'rented');
        $lease = Lease::create([
            'property_id' => $currentProperty->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1500,
            'status' => 'active',
        ]);
        $contractContent = '<p>O prazo da presente locação, iniciando-se em 14 de Agosto de 2026, para terminar em 14 de Agosto de 2027;</p>';
        $lease->contract()->create([
            'template_id' => $contract->id,
            'final_content' => $contractContent,
            'content_hash' => hash('sha256', $contractContent),
            'status' => 'finalized',
            'generated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leases.edit', $lease))
            ->assertOk()
            ->assertSee('name="start_date" value="2026-08-14"', false)
            ->assertSee('name="end_date" value="2027-08-14"', false)
            ->assertSee('Data identificada no contrato.')
            ->assertSee('Ativo')
            ->assertDontSee('Finalizado(Em Vigência)')
            ->assertSee('Encerrado')
            ->assertSee($currentProperty->title)
            ->assertSee($availableProperty->title)
            ->assertDontSee('Outro imóvel alugado');
    }

    public function test_active_lease_remains_in_force_and_closed_lease_releases_the_property(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Vigência',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-vigencia',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato de vigência',
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $client = Client::create([
            'name' => 'Cliente Vigência',
            'phone' => '81988880000',
            'cpf' => '987.654.321-00',
            'email' => 'vigencia@example.test',
            'status' => 'active',
        ]);
        $property = $this->createProperty($group, $contract, 'Imóvel em vigência', 'imovel-em-vigencia', 'available');
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1500,
            'status' => 'awaiting_completion',
        ]);
        $data = [
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1500,
        ];

        $this->actingAs($admin)
            ->put(route('admin.leases.update', $lease), [...$data, 'status' => 'active'])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $lease->fresh()->status);
        $this->assertTrue($lease->fresh()->isInForce());
        $this->assertSame('rented', $property->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.leases.update', $lease), [...$data, 'status' => 'closed'])
            ->assertRedirect(route('admin.leases.show', $lease))
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $lease->fresh()->status);
        $this->assertFalse($lease->fresh()->isInForce());
        $this->assertSame('available', $property->fresh()->status);
    }

    private function createProperty(
        PropertyGroup $group,
        Contract $contract,
        string $title,
        string $slug,
        string $status,
    ): Property {
        return Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Imóvel usado no teste da edição de aluguel.',
            'type' => 'residential',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'parking_spaces' => 1,
            'street' => 'Rua do Teste',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1500,
            'status' => $status,
            'has_solar_energy' => false,
        ]);
    }

    private function createClient(string $name, string $cpf): Client
    {
        return Client::create([
            'name' => $name,
            'phone' => '81988880000',
            'cpf' => $cpf,
            'rg' => '12.345.678-9',
            'profession' => 'Analista',
            'email' => null,
            'status' => 'active',
        ]);
    }
}

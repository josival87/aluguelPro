<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_lease_gets_an_independent_rendered_contract_snapshot(): void
    {
        $template = Contract::create([
            'title' => 'Contrato residencial',
            'content' => '<h1>Locação</h1><p>{{nome_cliente}} - {{cpf_cliente}} - {{rg_cliente}} - {{profissao_cliente}} - {{endereco_imovel}}</p>',
            'active' => true,
        ]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Centro',
            'responsible_name' => 'Maria Locadora',
            'phone' => '81999999999',
            'pix_key' => '81999999999',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $template->id,
            'title' => 'Apartamento 101',
            'slug' => 'apartamento-101',
            'description' => 'Apartamento residencial',
            'type' => 'residential',
            'street' => 'Rua Azul',
            'number' => '101',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1500,
            'status' => 'available',
        ]);
        $client = Client::create([
            'name' => 'João da Silva',
            'phone' => '81988888888',
            'cpf' => '123.456.789-00',
            'rg' => '12.345.678-9 SSP/PE',
            'profession' => 'Engenheiro civil',
            'email' => 'joao@example.test',
            'status' => 'active',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1500,
            'status' => 'awaiting_completion',
        ]);

        $leaseContract = app(ContractService::class)->generate($lease);

        $this->assertSame('in_production', $leaseContract->status);
        $this->assertSame($template->id, $leaseContract->template_id);
        $this->assertStringContainsString('João da Silva', $leaseContract->final_content);
        $this->assertStringContainsString('123.456.789-00', $leaseContract->final_content);
        $this->assertStringContainsString('12.345.678-9 SSP/PE', $leaseContract->final_content);
        $this->assertStringContainsString('Engenheiro civil', $leaseContract->final_content);
        $this->assertStringContainsString('Rua Azul 101', $leaseContract->final_content);
        $this->assertStringNotContainsString('{{nome_cliente}}', $leaseContract->final_content);

        $snapshot = $leaseContract->final_content;
        $template->update(['content' => '<p>Novo modelo {{nome_cliente}}</p>']);
        $this->assertSame($snapshot, $leaseContract->fresh()->final_content);
    }
}

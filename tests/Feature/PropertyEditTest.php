<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_generates_a_slug_when_the_optional_field_is_absent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = $this->createGroup();
        $contract = $this->createContract('Contrato para cadastro');

        $this->actingAs($admin)
            ->post(route('admin.properties.store'), $this->propertyData($group, $contract))
            ->assertRedirect(route('admin.properties.index'))
            ->assertSessionHasNoErrors();

        $property = Property::query()->sole();

        $this->assertStringStartsWith('apartamento-para-edicao-', $property->slug);
    }

    public function test_update_preserves_the_current_slug_when_the_optional_field_is_absent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = $this->createGroup();
        $contract = $this->createContract('Contrato para edição');
        $property = Property::create([
            ...$this->propertyData($group, $contract),
            'slug' => 'slug-existente',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.properties.update', $property), [
                ...$this->propertyData($group, $contract),
                'title' => 'Título atualizado',
            ])
            ->assertRedirect(route('admin.properties.show', $property))
            ->assertSessionHasNoErrors();

        $this->assertSame('slug-existente', $property->fresh()->slug);
    }

    public function test_edit_lists_active_contracts_and_the_property_current_inactive_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = $this->createGroup();
        $activeContract = $this->createContract('Contrato Ativo');
        $currentContract = $this->createContract('Contrato Atual Inativo', false);
        $this->createContract('Contrato Inativo Irrelevante', false);
        $property = Property::create([
            ...$this->propertyData($group, $currentContract),
            'slug' => 'apartamento-para-edicao',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.properties.edit', $property))
            ->assertOk()
            ->assertSee($activeContract->title)
            ->assertSee($currentContract->title)
            ->assertDontSee('Contrato Inativo Irrelevante');
    }

    private function createGroup(): PropertyGroup
    {
        return PropertyGroup::create([
            'name' => 'Grupo Centro',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-grupo-centro',
        ]);
    }

    private function createContract(string $title, bool $active = true): Contract
    {
        return Contract::create([
            'title' => $title,
            'content' => "Conteúdo de {$title}.",
            'active' => $active,
        ]);
    }

    /** @return array<string, mixed> */
    private function propertyData(PropertyGroup $group, Contract $contract): array
    {
        return [
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Apartamento para edição',
            'description' => 'Imóvel usado nos testes do formulário.',
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
            'status' => 'available',
            'has_solar_energy' => false,
        ];
    }
}

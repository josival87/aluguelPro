<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_delete_a_closed_lease_and_its_related_charges(): void
    {
        $lease = $this->lease('closed');
        $charge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $lease->client_id,
            'type' => 'rent',
            'generation_key' => 'rent:2026-09',
            'reference_month' => '2026-09-01',
            'due_date' => '2026-09-10',
            'amount' => 1200,
            'status' => 'paid',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee(route('admin.leases.destroy', $lease), false);

        $this->delete(route('admin.leases.destroy', $lease))
            ->assertRedirect(route('admin.leases.index'))
            ->assertSessionHas('success', 'Aluguel encerrado excluído.');

        $this->assertDatabaseMissing('leases', ['id' => $lease->id]);
        $this->assertDatabaseMissing('charges', ['id' => $charge->id]);
    }

    public function test_active_or_cancelled_leases_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        foreach (['active', 'cancelled'] as $status) {
            $lease = $this->lease($status);
            $this->actingAs($admin)
                ->delete(route('admin.leases.destroy', $lease))
                ->assertStatus(422);
            $this->assertDatabaseHas('leases', ['id' => $lease->id]);
        }
    }

    public function test_administrator_can_delete_an_unlinked_property_and_its_media(): void
    {
        $property = $this->property();
        $media = PropertyMedia::create([
            'property_id' => $property->id,
            'mime_type' => 'image/png',
            'media_base64' => base64_encode('imagem de teste'),
            'sort_order' => 1,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSee(route('admin.properties.destroy', $property), false);

        $this->delete(route('admin.properties.destroy', $property))
            ->assertRedirect(route('admin.properties.index'))
            ->assertSessionHas('success', 'Imóvel excluído.');

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
        $this->assertDatabaseMissing('property_media', ['id' => $media->id]);
    }

    public function test_property_with_a_lease_cannot_be_deleted(): void
    {
        $lease = $this->lease('closed');
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)
            ->delete(route('admin.properties.destroy', $lease->property))
            ->assertStatus(422);

        $this->assertDatabaseHas('properties', ['id' => $lease->property_id]);
    }

    public function test_manager_cannot_see_or_use_record_deletion_actions(): void
    {
        $lease = $this->lease('closed');
        $property = $this->property();
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);

        $this->actingAs($manager)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertDontSee('<form method="post" action="'.route('admin.leases.destroy', $lease).'"', false);
        $this->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertDontSee('<form method="post" action="'.route('admin.properties.destroy', $property).'"', false);
        $this->delete(route('admin.leases.destroy', $lease))->assertForbidden();
        $this->delete(route('admin.properties.destroy', $property))->assertForbidden();
    }

    private function lease(string $status): Lease
    {
        $property = $this->property();
        $suffix = uniqid();
        $client = Client::create([
            'name' => 'Cliente '.$suffix,
            'phone' => '81988888888',
            'cpf' => sprintf('%011u', crc32($suffix)),
            'status' => 'active',
        ]);

        return Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1200,
            'status' => $status,
        ]);
    }

    private function property(): Property
    {
        $suffix = uniqid();
        $group = PropertyGroup::create([
            'name' => 'Grupo '.$suffix,
            'responsible_name' => 'Responsável',
            'phone' => '81999999999',
            'pix_key' => 'pix-'.$suffix,
        ]);
        $contract = Contract::create([
            'title' => 'Contrato '.$suffix,
            'content' => 'Conteúdo de teste.',
            'active' => true,
        ]);

        return Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Imóvel '.$suffix,
            'slug' => 'imovel-'.$suffix,
            'description' => 'Imóvel para testes de exclusão.',
            'type' => 'residential',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'parking_spaces' => 1,
            'street' => 'Rua do Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1200,
            'status' => 'available',
            'has_solar_energy' => false,
        ]);
    }
}

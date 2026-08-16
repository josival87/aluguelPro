<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\LeaseContract;
use App\Models\Property;
use App\Models\PropertyGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InterestedApplicationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_password_returns_a_clear_message_in_portuguese(): void
    {
        $property = $this->createProperty();

        $response = $this->from(route('properties.application', $property))
            ->post(route('properties.apply', $property), $this->validApplication([
                'password' => '1234567',
                'password_confirmation' => '1234567',
            ]));

        $response->assertRedirect(route('properties.application', $property))
            ->assertSessionHasErrors([
                'password' => 'A senha deve ter pelo menos 8 caracteres.',
            ]);
    }

    public function test_password_confirmation_and_application_page_explain_how_to_fix_the_input(): void
    {
        $property = $this->createProperty();

        $this->get(route('properties.application', $property))
            ->assertOk()
            ->assertSee('name="rg"', false)
            ->assertSee('name="profession"', false)
            ->assertSee('Use pelo menos 8 caracteres.')
            ->assertSee('Digite exatamente a mesma senha.')
            ->assertSee('minlength="8"', false);

        $this->from(route('properties.application', $property))
            ->post(route('properties.apply', $property), $this->validApplication([
                'password_confirmation' => 'senha-diferente',
            ]))
            ->assertSessionHasErrors([
                'password' => 'A confirmação da senha não confere. Digite a mesma senha nos dois campos.',
            ]);
    }

    public function test_application_stores_rg_and_profession_and_adds_them_to_the_contract(): void
    {
        $property = $this->createProperty();

        $this->post(route('properties.apply', $property), $this->validApplication())
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $client = Client::query()->sole();
        $contract = LeaseContract::query()->sole();

        $this->assertSame('12.345.678-9 SSP/PE', $client->rg);
        $this->assertSame('Analista de sistemas', $client->profession);
        $this->assertStringContainsString('12.345.678-9 SSP/PE', $contract->final_content);
        $this->assertStringContainsString('Analista de sistemas', $contract->final_content);
    }

    /** @param array<string, mixed> $overrides */
    private function validApplication(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Interessado Teste',
            'phone' => '81999990000',
            'cpf' => '123.456.789-00',
            'rg' => '12.345.678-9 SSP/PE',
            'profession' => 'Analista de sistemas',
            'email' => 'interessado@example.com',
            'family_income' => 3500,
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'document' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            'privacy' => '1',
        ], $overrides);
    }

    private function createProperty(): Property
    {
        $group = PropertyGroup::create([
            'name' => 'Grupo Interessados',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-interessados',
        ]);
        $contract = Contract::create([
            'title' => 'Contrato para interessados',
            'content' => '<p>RG {{rg_cliente}} · Profissão {{profissao_cliente}}</p>',
            'active' => true,
        ]);

        return Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Imóvel para interessados',
            'slug' => 'imovel-para-interessados',
            'description' => 'Imóvel disponível para cadastro.',
            'type' => 'residential',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'parking_spaces' => 1,
            'street' => 'Rua dos Interessados',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1200,
            'status' => 'available',
            'has_solar_energy' => false,
        ]);
    }
}

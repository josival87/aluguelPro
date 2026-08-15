<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractSignature;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\OtpCode;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContractSignaturePhotoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_photo_is_required_to_sign(): void
    {
        $flow = $this->createSignatureFlow();
        $this->createOtp($flow['contract'], 'client', '123456', $flow['client']->phone);

        $this->actingAs($flow['clientUser'])
            ->get(route('contracts.show', $flow['contract']))
            ->assertOk()
            ->assertSee('Foto atual do locatário')
            ->assertSee('Confirmar assinatura e foto');

        $this->actingAs($flow['clientUser'])
            ->post(route('contracts.sign', $flow['contract']), [
                'code' => '123456',
                'accepted' => '1',
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('contract_signatures', 0);
    }

    public function test_admin_cannot_start_signature_before_tenant_photo_and_signature(): void
    {
        $flow = $this->createSignatureFlow();

        $this->actingAs($flow['admin'])
            ->post(route('contracts.otp', $flow['contract']))
            ->assertStatus(422);

        $this->actingAs($flow['admin'])
            ->get(route('contracts.show', $flow['contract']))
            ->assertOk()
            ->assertSee('O locatário precisa confirmar o código do WhatsApp')
            ->assertDontSee('Assinar e concluir contrato');

        $this->assertDatabaseMissing('otp_codes', [
            'contract_id' => $flow['contract']->id,
            'signer_type' => 'responsible',
        ]);
    }

    public function test_tenant_photo_is_stored_and_admin_signature_finishes_the_contract(): void
    {
        $unrelatedProperty = $this->createUnrelatedOlderLease();
        $flow = $this->createSignatureFlow();
        $photoContents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $photo = UploadedFile::fake()->createWithContent('foto-locatario.png', $photoContents);

        $this->createOtp($flow['contract'], 'client', '123456', $flow['client']->phone);

        $this->actingAs($flow['clientUser'])
            ->post(route('contracts.sign', $flow['contract']), [
                'code' => '123456',
                'accepted' => '1',
                'photo' => $photo,
            ])
            ->assertSessionHasNoErrors();

        $tenantSignature = ContractSignature::query()
            ->where('contract_id', $flow['contract']->id)
            ->where('signer_type', 'client')
            ->sole();
        $photoSha256 = hash('sha256', $photoContents);

        $this->assertSame('image/png', $tenantSignature->photo_mime_type);
        $this->assertSame($photoSha256, $tenantSignature->photo_sha256);
        $this->assertStringStartsWith('data:image/png;base64,', $tenantSignature->photo_base64);
        $this->assertSame($photoSha256, $flow['contract']->fresh()->tenant_signature['photo_sha256']);
        $this->assertSame('awaiting_signatures', $flow['contract']->fresh()->status);
        $this->assertSame('awaiting_signatures', $flow['lease']->fresh()->status);

        $this->actingAs($flow['admin'])
            ->get(route('contracts.show', $flow['contract']))
            ->assertOk()
            ->assertSee('Foto autenticada')
            ->assertSee('Assinar e concluir contrato');

        $this->createOtp($flow['contract'], 'responsible', '654321', $flow['admin']->phone);

        $this->actingAs($flow['admin'])
            ->post(route('contracts.sign', $flow['contract']), [
                'code' => '654321',
                'accepted' => '1',
            ])
            ->assertSessionHasNoErrors();

        $adminSignature = ContractSignature::query()
            ->where('contract_id', $flow['contract']->id)
            ->where('signer_type', 'responsible')
            ->sole();

        $this->assertSame($flow['admin']->name, $adminSignature->signer_name);
        $this->assertSame($flow['admin']->cpf, $adminSignature->signer_document);
        $this->assertNull($adminSignature->photo_base64);
        $this->assertSame('signed', $flow['contract']->fresh()->status);
        $this->assertNotNull($flow['contract']->fresh()->signed_at);
        $this->assertSame('active', $flow['lease']->fresh()->status);
        $this->assertSame('rented', $flow['property']->fresh()->status);
        $this->assertSame('available', $unrelatedProperty->fresh()->status);
    }

    private function createUnrelatedOlderLease(): Property
    {
        $client = Client::create([
            'name' => 'Cliente de outra locação',
            'phone' => '81970000000',
            'cpf' => '000.111.222-33',
            'status' => 'active',
        ]);
        $group = PropertyGroup::create([
            'name' => 'Grupo de outra locação',
            'responsible_name' => 'Responsável anterior',
            'phone' => '81971111111',
            'pix_key' => 'pix-anterior',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Imóvel de outra locação',
            'slug' => 'imovel-outra-locacao',
            'description' => 'Registro anterior que não pode ser alterado.',
            'type' => 'residential',
            'street' => 'Rua Anterior',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1000,
            'status' => 'available',
        ]);
        Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1000,
            'status' => 'awaiting_completion',
        ]);

        return $property;
    }

    /** @return array{admin: User, clientUser: User, client: Client, property: Property, lease: Lease, contract: LeaseContract} */
    private function createSignatureFlow(): array
    {
        $admin = User::factory()->create([
            'name' => 'Administrador Assinante',
            'cpf' => '111.222.333-44',
            'phone' => '81999990000',
            'role' => 'admin',
            'active' => true,
        ]);
        $clientUser = User::factory()->create([
            'name' => 'Locatário Teste',
            'cpf' => '555.666.777-88',
            'phone' => '81988880000',
            'role' => 'client',
            'active' => true,
        ]);
        $client = Client::create([
            'user_id' => $clientUser->id,
            'name' => 'Locatário Teste',
            'phone' => '81988880000',
            'cpf' => '555.666.777-88',
            'email' => $clientUser->email,
            'status' => 'active',
        ]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Teste',
            'responsible_name' => 'Responsável Teste',
            'phone' => '81977770000',
            'pix_key' => 'pix-teste',
        ]);
        $template = Contract::create([
            'title' => 'Contrato para assinatura com foto',
            'content' => '<p>Contrato de teste</p>',
            'active' => true,
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $template->id,
            'title' => 'Imóvel Teste',
            'slug' => 'imovel-teste-assinatura',
            'description' => 'Imóvel para testar assinatura',
            'type' => 'residential',
            'street' => 'Rua Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1500,
            'status' => 'paused',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1500,
            'status' => 'awaiting_signatures',
        ]);
        $contract = LeaseContract::create([
            'lease_id' => $lease->id,
            'template_id' => $template->id,
            'final_content' => '<p>Contrato final de teste</p>',
            'content_hash' => hash('sha256', '<p>Contrato final de teste</p>'),
            'status' => 'awaiting_signatures',
            'generated_at' => now(),
        ]);

        return compact('admin', 'clientUser', 'client', 'property', 'lease', 'contract');
    }

    private function createOtp(LeaseContract $contract, string $type, string $code, string $phone): void
    {
        OtpCode::create([
            'contract_id' => $contract->id,
            'signer_type' => $type,
            'phone' => $phone,
            'code_hash' => hash('sha256', $code.'|'.config('app.key')),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}

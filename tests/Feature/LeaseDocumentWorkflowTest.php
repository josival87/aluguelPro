<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeaseDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_attach_and_download_multiple_lease_documents(): void
    {
        $admin = User::factory()->create();
        $lease = $this->createLease();
        $contractContents = "%PDF-1.4\nContrato antigo assinado";
        $notesContents = "Observações do aluguel antigo";

        $response = $this->actingAs($admin)->post(route('admin.leases.documents.store', $lease), [
            'category' => 'legacy_contract',
            'description' => 'Documentos recebidos do arquivo físico',
            'documents' => [
                UploadedFile::fake()->createWithContent('contrato-antigo.pdf', $contractContents),
                UploadedFile::fake()->createWithContent('observacoes.txt', $notesContents),
            ],
        ]);

        $response->assertRedirect(route('admin.leases.show', $lease));
        $this->assertCount(2, $lease->fresh()->documents);

        $contract = LeaseDocument::where('original_name', 'contrato-antigo.pdf')->firstOrFail();
        $this->assertSame($lease->id, $contract->lease_id);
        $this->assertSame($admin->id, $contract->uploaded_by);
        $this->assertSame('legacy_contract', $contract->category);
        $this->assertSame($contractContents, base64_decode($contract->document_base64, true));
        $this->assertSame(hash('sha256', $contractContents), $contract->checksum_sha256);

        $download = $this->actingAs($admin)->get(route('admin.leases.documents.download', [$lease, $contract]));
        $download->assertOk()->assertDownload('contrato-antigo.pdf');
        $this->assertSame($contractContents, $download->streamedContent());

        $otherLease = $this->createLease('imovel-secundario');
        $this->actingAs($admin)
            ->get(route('admin.leases.documents.download', [$otherLease, $contract]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Anexar documentos')
            ->assertSee('Baixar')
            ->assertDontSee('Remover este documento da ficha do aluguel?');

        $this->assertNull(app('router')->getRoutes()->getByName('admin.leases.documents.destroy'));
        $this->assertDatabaseHas('lease_documents', ['id' => $contract->id]);
    }

    public function test_client_cannot_access_admin_lease_documents(): void
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $lease = $this->createLease();

        $this->actingAs($clientUser)->post(route('admin.leases.documents.store', $lease), [
            'category' => 'other',
            'documents' => [UploadedFile::fake()->createWithContent('arquivo.txt', 'conteúdo')],
        ])->assertForbidden();

        $this->assertDatabaseCount('lease_documents', 0);
    }

    private function createLease(string $slug = 'imovel-principal'): Lease
    {
        $group = PropertyGroup::create([
            'name' => 'Grupo '.$slug,
            'responsible_name' => 'Responsável',
            'phone' => '81999999999',
            'pix_key' => '81999999999',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Imóvel '.$slug,
            'slug' => $slug,
            'description' => 'Imóvel para teste',
            'type' => 'residential',
            'street' => 'Rua de Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1000,
            'status' => 'available',
        ]);
        $client = Client::create([
            'name' => 'Cliente '.$slug,
            'phone' => '81988888888',
            'cpf' => $slug === 'imovel-principal' ? '123.456.789-00' : '987.654.321-00',
            'email' => $slug.'@example.test',
            'status' => 'active',
        ]);

        return Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1000,
            'status' => 'awaiting_completion',
        ]);
    }
}

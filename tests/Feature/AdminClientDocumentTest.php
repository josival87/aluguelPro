<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminClientDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_a_client_document_from_the_client_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $client = $this->createClient('123.456.789-00');
        $contents = '%PDF-1.4 documento de identificação';
        $document = $client->documents()->create([
            'type' => 'identification',
            'original_name' => 'documento-cliente.pdf',
            'mime_type' => 'application/pdf',
            'document_base64' => base64_encode($contents),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('documento-cliente.pdf')
            ->assertSee('Abrir documento')
            ->assertSee('Adicionar documento')
            ->assertSee(route('admin.clients.edit', $client).'#documents-upload', false)
            ->assertSee(route('admin.clients.documents.show', [$client, $document]), false);

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.documents.show', [$client, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame($contents, $response->getContent());
    }

    public function test_admin_cannot_open_a_document_through_another_client_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $client = $this->createClient('123.456.789-00');
        $otherClient = $this->createClient('987.654.321-00');
        $document = $client->documents()->create([
            'type' => 'identification',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'document_base64' => base64_encode('documento'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.documents.show', [$otherClient, $document]))
            ->assertNotFound();
    }

    public function test_admin_can_upload_documents_when_creating_and_editing_a_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $firstContents = '%PDF-1.4 primeiro documento';

        $this->actingAs($admin)
            ->post(route('admin.clients.store'), [
                ...$this->clientData('123.456.789-00'),
                'documents' => [UploadedFile::fake()->createWithContent('rg-frente.pdf', $firstContents)],
            ])
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHasNoErrors();

        $client = Client::query()->sole();
        $firstDocument = $client->documents()->sole();
        $this->assertSame('rg-frente.pdf', $firstDocument->original_name);
        $this->assertSame($firstContents, base64_decode($firstDocument->document_base64, true));

        $this->actingAs($admin)
            ->get(route('admin.clients.edit', $client))
            ->assertOk()
            ->assertSee('Documentos existentes')
            ->assertSee('rg-frente.pdf')
            ->assertSee('Abrir')
            ->assertDontSee('Apagar')
            ->assertSee('name="documents[]"', false)
            ->assertSee('class="field" id="documents-upload"', false)
            ->assertSeeInOrder([
                'Status',
                'Adicionar documentos',
                'Senha (opcional)',
                'Confirmar senha',
                'Salvar cliente',
                'Documentos existentes',
            ])
            ->assertSee('enctype="multipart/form-data"', false);

        $this->actingAs($admin)
            ->put(route('admin.clients.update', $client), [
                ...$this->clientData('123.456.789-00'),
                'documents' => [UploadedFile::fake()->createWithContent(
                    'rg-verso.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
                )],
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHasNoErrors();

        $this->assertCount(2, $client->fresh()->documents);
    }

    public function test_client_documents_do_not_have_a_deletion_route(): void
    {
        $client = $this->createClient('123.456.789-00');
        $document = $client->documents()->create([
            'type' => 'identification',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'document_base64' => base64_encode('documento'),
        ]);

        $this->assertNull(app('router')->getRoutes()->getByName('admin.clients.documents.destroy'));
        $this->assertDatabaseHas('client_documents', ['id' => $document->id]);
    }

    private function createClient(string $cpf): Client
    {
        return Client::create($this->clientData($cpf));
    }

    /** @return array<string, mixed> */
    private function clientData(string $cpf): array
    {
        return [
            'name' => 'Cliente com documento',
            'phone' => '81999990000',
            'cpf' => $cpf,
            'rg' => '12.345.678-9',
            'profession' => 'Analista',
            'email' => null,
            'family_income' => 5000,
            'status' => 'active',
        ];
    }
}

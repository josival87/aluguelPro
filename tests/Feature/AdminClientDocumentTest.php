<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function createClient(string $cpf): Client
    {
        return Client::create([
            'name' => 'Cliente com documento',
            'phone' => '81999990000',
            'cpf' => $cpf,
            'rg' => '12.345.678-9',
            'profession' => 'Analista',
            'email' => null,
            'family_income' => 5000,
            'status' => 'active',
        ]);
    }
}

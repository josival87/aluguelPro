<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_view_and_update_the_allowed_personal_data_without_changing_cpf(): void
    {
        [$user, $client] = $this->createClientAccount('123.456.789-00');

        $this->actingAs($user)
            ->get(route('client.profile.edit'))
            ->assertOk()
            ->assertSee('Dados pessoais')
            ->assertSee('Cliente do Portal')
            ->assertSee('123.456.789-00')
            ->assertSee('O CPF não pode ser alterado pelo portal.')
            ->assertSee('name="rg"', false)
            ->assertSee('name="profession"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="family_income"', false)
            ->assertDontSee('name="cpf"', false)
            ->assertDontSee('name="name"', false);

        $this->actingAs($user)
            ->put(route('client.profile.update'), [
                'email' => 'novo-email@example.test',
                'rg' => '98.765.432-1',
                'profession' => 'Engenheira civil',
                'phone' => '81977776666',
                'family_income' => 7250.50,
                'cpf' => '987.654.321-00',
                'name' => 'Nome adulterado',
                'status' => 'rejected',
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Dados pessoais atualizados.');

        $client->refresh();
        $user->refresh();

        $this->assertSame('Cliente do Portal', $client->name);
        $this->assertSame('12345678900', $client->cpf);
        $this->assertSame('active', $client->status);
        $this->assertSame('98.765.432-1', $client->rg);
        $this->assertSame('Engenheira civil', $client->profession);
        $this->assertSame('81977776666', $client->phone);
        $this->assertSame('novo-email@example.test', $client->email);
        $this->assertSame('7250.50', $client->family_income);
        $this->assertSame('12345678900', $user->cpf);
        $this->assertSame('12345678900', $user->login);
        $this->assertSame('81977776666', $user->phone);
        $this->assertSame('novo-email@example.test', $user->email);
    }

    public function test_client_can_upload_list_and_open_only_their_own_documents(): void
    {
        [$user, $client] = $this->createClientAccount('123.456.789-00');
        [, $otherClient] = $this->createClientAccount('987.654.321-00', 'outro@example.test');
        $existingContents = '%PDF-1.4 documento existente';
        $existingDocument = $client->documents()->create([
            'type' => 'identification',
            'original_name' => 'cpf-cliente.pdf',
            'mime_type' => 'application/pdf',
            'document_base64' => base64_encode($existingContents),
        ]);
        $otherDocument = $otherClient->documents()->create([
            'type' => 'identification',
            'original_name' => 'documento-de-outro-cliente.pdf',
            'mime_type' => 'application/pdf',
            'document_base64' => base64_encode('documento privado'),
        ]);
        $newContents = '%PDF-1.4 novo documento';

        $this->actingAs($user)
            ->get(route('client.profile.edit'))
            ->assertOk()
            ->assertSee('Meus documentos')
            ->assertSee('Documentos cadastrados')
            ->assertSee('cpf-cliente.pdf')
            ->assertSee(route('client.documents.show', $existingDocument), false)
            ->assertSee('name="documents[]"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->actingAs($user)
            ->post(route('client.documents.store'), [
                'documents' => [UploadedFile::fake()->createWithContent('comprovante.pdf', $newContents)],
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHasNoErrors();

        $newDocument = $client->documents()->where('original_name', 'comprovante.pdf')->sole();
        $this->assertSame($newContents, base64_decode($newDocument->document_base64, true));

        $response = $this->actingAs($user)
            ->get(route('client.documents.show', $existingDocument))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame($existingContents, $response->getContent());

        $this->actingAs($user)
            ->get(route('client.documents.show', $otherDocument))
            ->assertNotFound();
    }

    public function test_non_client_cannot_access_the_client_personal_data_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('client.profile.edit'))
            ->assertForbidden();
    }

    /** @return array{User, Client} */
    private function createClientAccount(string $cpf, string $email = 'cliente@example.test'): array
    {
        $user = User::factory()->create([
            'name' => 'Cliente do Portal',
            'email' => $email,
            'login' => preg_replace('/\D/', '', $cpf),
            'cpf' => $cpf,
            'phone' => '81999990000',
            'role' => 'client',
            'active' => true,
        ]);
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Cliente do Portal',
            'phone' => '81999990000',
            'cpf' => $cpf,
            'rg' => '12.345.678-9',
            'profession' => 'Analista',
            'email' => $email,
            'family_income' => 5000,
            'status' => 'active',
        ]);

        return [$user, $client];
    }
}

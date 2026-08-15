<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CompanySingletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_created_once_and_subsequent_saves_update_the_same_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->put(route('admin.company.update'), [
            'name' => 'Empresa Original',
            'cnpj' => '12.345.678/0001-90',
            'phone' => '81999990000',
            'email' => 'original@example.test',
            'pix_key' => 'pix-original',
        ])->assertSessionHasNoErrors();

        $companyId = Company::query()->sole()->id;

        $this->actingAs($admin)->put(route('admin.company.update'), [
            'name' => 'Empresa Atualizada',
            'cnpj' => '98.765.432/0001-10',
            'phone' => '81988880000',
            'email' => 'atualizada@example.test',
            'pix_key' => 'pix-atualizada',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Company::query()->count());
        $this->assertSame($companyId, Company::query()->sole()->id);
        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'name' => 'Empresa Atualizada',
            'cnpj' => '98.765.432/0001-10',
            'singleton' => true,
        ]);
    }

    public function test_company_cannot_be_deleted_through_the_model(): void
    {
        $company = Company::create([
            'name' => 'Empresa Permanente',
            'cnpj' => '12.345.678/0001-90',
            'phone' => '81999990000',
            'email' => 'empresa@example.test',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('não pode ser excluído');

        $company->delete();
    }

    public function test_database_rejects_a_second_company(): void
    {
        Company::create([
            'name' => 'Empresa Principal',
            'cnpj' => '12.345.678/0001-90',
            'phone' => '81999990000',
            'email' => 'principal@example.test',
        ]);

        $this->expectException(QueryException::class);

        Company::create([
            'name' => 'Empresa Duplicada',
            'cnpj' => '98.765.432/0001-10',
            'phone' => '81988880000',
            'email' => 'duplicada@example.test',
        ]);
    }
}

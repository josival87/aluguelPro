<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\PixPayment;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeasePixTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_static_pix_for_an_open_lease_charge(): void
    {
        [$admin, $lease, $openCharge, $paidCharge] = $this->leaseWithCharges();

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Gerar Pix')
            ->assertSee(route('admin.charges.pix', $openCharge), false)
            ->assertDontSee(route('admin.charges.pix', $paidCharge), false);

        $this->actingAs($admin)
            ->post(route('admin.charges.pix', $openCharge))
            ->assertRedirect(route('admin.leases.show', $lease).'#pix-gerado')
            ->assertSessionHas('pix_payment_id')
            ->assertSessionHas('success');

        $payment = PixPayment::query()->sole();
        $payload = $payment->br_code;

        $this->assertSame($openCharge->id, $payment->charge_id);
        $this->assertSame('local_emv_static', $payment->provider);
        $this->assertSame('active', $payment->status);
        $this->assertEquals(900.0, $payment->total_amount);
        $this->assertStringStartsWith('00020126', $payload);
        $this->assertStringContainsString('5303986', $payload);
        $this->assertStringContainsString('5406900.00', $payload);
        $this->assertStringContainsString('5802BR', $payload);
        $this->assertMatchesRegularExpression('/6304[0-9A-F]{4}$/', $payload);
        $this->assertSame(substr($payload, -4), $this->crc16(substr($payload, 0, -4)));

        $this->get(route('admin.leases.show', $lease))
            ->assertOk()
            ->assertSee('Pix copia e cola')
            ->assertSee('Pix estático')
            ->assertSee($payload);
    }

    public function test_pix_cannot_be_generated_for_a_paid_charge(): void
    {
        [$admin, , , $paidCharge] = $this->leaseWithCharges();

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $paidCharge->lease_id))
            ->post(route('admin.charges.pix', $paidCharge))
            ->assertRedirect(route('admin.leases.show', $paidCharge->lease_id))
            ->assertSessionHasErrors('pix');

        $this->assertDatabaseCount('pix_payments', 0);
    }

    public function test_client_cannot_use_the_admin_pix_endpoint(): void
    {
        [, , $openCharge] = $this->leaseWithCharges();
        $clientUser = User::factory()->create(['role' => 'client', 'active' => true]);

        $this->actingAs($clientUser)
            ->post(route('admin.charges.pix', $openCharge))
            ->assertForbidden();
    }

    public function test_client_uses_the_same_static_pix_generator_and_ignores_legacy_codes(): void
    {
        [, , $openCharge] = $this->leaseWithCharges();
        $clientUser = User::factory()->create(['role' => 'client', 'active' => true]);
        $openCharge->client->update(['user_id' => $clientUser->id]);
        $openCharge->pixPayments()->create([
            'txid' => 'LEGACYCODE',
            'original_amount' => 900,
            'fine_amount' => 0,
            'interest_amount' => 0,
            'total_amount' => 900,
            'br_code' => 'CODIGO-ANTIGO-INVALIDO',
            'provider' => 'local_emv',
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($clientUser)
            ->get(route('client.charge', $openCharge))
            ->assertOk()
            ->assertSee('Gerar Pix copia e cola')
            ->assertDontSee('CODIGO-ANTIGO-INVALIDO');

        $this->post(route('client.pix', $openCharge))
            ->assertRedirect(route('client.charge', $openCharge))
            ->assertSessionHas('payment_id');

        $payment = $openCharge->pixPayments()->where('provider', 'local_emv_static')->sole();
        $this->assertStringStartsWith('00020126', $payment->br_code);
        $this->assertSame(substr($payment->br_code, -4), $this->crc16(substr($payment->br_code, 0, -4)));
    }

    public function test_invalid_group_key_is_rejected_instead_of_generating_an_unpayable_code(): void
    {
        [$admin, , $openCharge] = $this->leaseWithCharges();
        $openCharge->lease->property->group->update(['pix_key' => 'pix-grupo-05']);

        $this->actingAs($admin)
            ->get(route('admin.leases.show', $openCharge->lease_id))
            ->assertOk()
            ->assertSee('A chave Pix deste grupo ainda não tem um formato válido.')
            ->assertSee(route('admin.groups.edit', $openCharge->lease->property->group), false);

        $this->actingAs($admin)
            ->from(route('admin.leases.show', $openCharge->lease_id))
            ->post(route('admin.charges.pix', $openCharge))
            ->assertRedirect(route('admin.leases.show', $openCharge->lease_id))
            ->assertSessionHasErrors('pix');

        $this->assertDatabaseCount('pix_payments', 0);
    }

    private function leaseWithCharges(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Pix',
            'responsible_name' => 'João da Silva',
            'phone' => '81999990000',
            'pix_key' => 'financeiro@example.test',
        ]);
        $client = Client::create([
            'name' => 'Cliente Pix',
            'phone' => '81988880000',
            'cpf' => '123.456.789-00',
            'email' => 'cliente-pix@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Apartamento Pix',
            'slug' => 'apartamento-pix',
            'description' => 'Imóvel usado no teste do Pix.',
            'type' => 'residential',
            'street' => 'Rua do Pix',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 900,
            'status' => 'rented',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 900,
            'status' => 'active',
        ]);
        $openCharge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'rent',
            'reference_month' => now()->startOfMonth(),
            'due_date' => now()->addDay(),
            'amount' => 900,
            'status' => 'open',
        ]);
        $paidCharge = Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $client->id,
            'type' => 'solar',
            'reference_month' => now()->subMonth()->startOfMonth(),
            'due_date' => now()->subMonth()->addDay(),
            'amount' => 100,
            'status' => 'paid',
        ]);

        return [$admin, $lease, $openCharge, $paidCharge];
    }

    private function crc16(string $payload): string
    {
        $crc = 0xFFFF;
        foreach (str_split($payload) as $character) {
            $crc ^= ord($character) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\SendMiaReceipt;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\MiaReceipt;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use App\Services\ChargePaymentService;
use App\Services\MiaReceiptClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MiaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rent_and_solar_settlements_for_melo_jr_create_idempotent_outbox_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 14:30:00', 'America/Sao_Paulo'));
        Queue::fake();
        [$group, $lease] = $this->lease('Grupo Melo Jr', 'melo-jr');
        $this->configureMia($group);

        $rent = $this->charge($lease, 'rent', '2026-08-01', '1850.00');
        $solar = $this->charge($lease, 'solar', '2026-09-01', '117.35');
        $payments = app(ChargePaymentService::class);

        $payments->settle($rent, 'manual');
        $payments->settle($solar, 'ai_agent');

        $this->assertDatabaseCount('mia_receipts', 2);
        $rentReceipt = MiaReceipt::where('charge_id', $rent->id)->firstOrFail();
        $solarReceipt = MiaReceipt::where('charge_id', $solar->id)->firstOrFail();

        $this->assertSame([
            'external_id' => 'alugapro:charge:'.$rent->id,
            'title' => 'Aluguel recebido',
            'description' => 'Aluguel de Imóvel melo-jr, competência 08/2026, locatário Cliente melo-jr.',
            'amount' => '1850.00',
            'occurred_on' => '2026-08-30',
        ], $rentReceipt->payload);
        $this->assertSame('Energia solar recebida', $solarReceipt->payload['title']);
        $this->assertSame('117.35', $solarReceipt->payload['amount']);
        $this->assertSame(MiaReceipt::STATUS_PENDING, $solarReceipt->status);

        Queue::assertPushed(SendMiaReceipt::class, 2);
        Queue::assertPushed(SendMiaReceipt::class, fn (SendMiaReceipt $job): bool => $job->miaReceiptId === $rentReceipt->id);

        $duplicate = $payments->settle($rent, 'manual');
        $this->assertFalse($duplicate['changed']);
        $this->assertDatabaseCount('mia_receipts', 2);
        Queue::assertPushed(SendMiaReceipt::class, 2);
    }

    public function test_settlements_outside_the_configured_group_and_waivers_are_not_sent(): void
    {
        Queue::fake();
        [$meloGroup, $meloLease] = $this->lease('Melo Jr', 'melo');
        [, $otherLease] = $this->lease('Grupo Centro', 'centro');
        $this->configureMia($meloGroup);
        $payments = app(ChargePaymentService::class);

        $payments->settle($this->charge($otherLease, 'rent', '2026-08-01', '900.00'));
        $payments->waive($this->charge($meloLease, 'solar', '2026-09-01', '75.00'));

        $this->assertDatabaseCount('mia_receipts', 0);
        Queue::assertNothingPushed();
    }

    public function test_paid_one_off_charge_uses_the_same_mia_integration_flow(): void
    {
        Queue::fake();
        [$group, $lease] = $this->lease('Melo Jr', 'avulsa');
        $this->configureMia($group);
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);

        $this->actingAs($admin)->post(route('admin.leases.charges.store', $lease), [
            'type' => 'solar',
            'amount' => '89.50',
            'due_date' => '2026-09-22',
            'status' => 'paid',
        ])->assertRedirect();

        $charge = Charge::latest('id')->firstOrFail();
        $this->assertSame('paid', $charge->status);
        $this->assertDatabaseHas('mia_receipts', [
            'charge_id' => $charge->id,
            'external_id' => 'alugapro:charge:'.$charge->id,
            'status' => MiaReceipt::STATUS_PENDING,
        ]);
        Queue::assertPushed(SendMiaReceipt::class, 1);
    }

    public function test_job_posts_the_snapshot_with_bearer_authentication_and_marks_it_sent(): void
    {
        $receipt = $this->pendingReceipt();
        Http::fake([
            'https://mia.example/api/v1/clientes/42/recebimentos' => Http::response([
                'data' => ['id' => 731],
                'meta' => ['created' => true],
            ], 201),
        ]);

        (new SendMiaReceipt($receipt->id))->handle(app(MiaReceiptClient::class));

        $receipt->refresh();
        $this->assertSame(MiaReceipt::STATUS_SENT, $receipt->status);
        $this->assertSame(731, $receipt->mia_receipt_id);
        $this->assertSame(201, $receipt->last_http_status);
        $this->assertSame(1, $receipt->attempts);
        $this->assertNotNull($receipt->sent_at);
        Http::assertSent(function (Request $request) use ($receipt): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://mia.example/api/v1/clientes/42/recebimentos'
                && $request->hasHeader('Authorization', 'Bearer mia-secret')
                && $request->data() === $receipt->payload;
        });
    }

    public function test_job_confirms_by_external_id_after_an_uncertain_server_error(): void
    {
        $receipt = $this->pendingReceipt();
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST') {
                return Http::response(['message' => 'Falha temporária.'], 500);
            }

            return Http::response(['data' => ['id' => 845]], 200);
        });

        (new SendMiaReceipt($receipt->id))->handle(app(MiaReceiptClient::class));

        $receipt->refresh();
        $this->assertSame(MiaReceipt::STATUS_SENT, $receipt->status);
        $this->assertSame(845, $receipt->mia_receipt_id);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/recebimentos/alugapro%3Acharge%3A999'));
    }

    public function test_job_does_not_retry_a_validation_error(): void
    {
        $receipt = $this->pendingReceipt();
        Http::fake([
            '*' => Http::response([
                'message' => 'Dados inválidos.',
                'errors' => ['amount' => ['Valor inválido.']],
            ], 422),
        ]);

        (new SendMiaReceipt($receipt->id))->handle(app(MiaReceiptClient::class));

        $receipt->refresh();
        $this->assertSame(MiaReceipt::STATUS_FAILED, $receipt->status);
        $this->assertSame(422, $receipt->last_http_status);
        $this->assertStringContainsString('Campos: amount', $receipt->last_error);
        Http::assertSentCount(1);
    }

    public function test_failed_receipt_can_be_requeued_without_changing_its_snapshot(): void
    {
        Queue::fake();
        $receipt = $this->pendingReceipt();
        $originalPayload = $receipt->payload;
        $receipt->update([
            'status' => MiaReceipt::STATUS_FAILED,
            'last_http_status' => 401,
            'last_error' => 'Credencial inválida.',
        ]);
        config([
            'services.mia.enabled' => true,
            'services.mia.client_id' => 42,
        ]);

        $this->artisan('mia:retry-receipt', ['receipt' => $receipt->id])
            ->assertSuccessful()
            ->expectsOutput('Recebimento reenfileirado com o mesmo external_id e o mesmo conteúdo.');

        $receipt->refresh();
        $this->assertSame(MiaReceipt::STATUS_PENDING, $receipt->status);
        $this->assertSame($originalPayload, $receipt->payload);
        $this->assertNull($receipt->last_error);
        Queue::assertPushed(SendMiaReceipt::class, fn (SendMiaReceipt $job): bool => $job->miaReceiptId === $receipt->id);
    }

    private function configureMia(PropertyGroup $group): void
    {
        config([
            'services.mia.enabled' => true,
            'services.mia.url' => 'https://mia.example',
            'services.mia.token' => 'mia-secret',
            'services.mia.client_id' => 42,
            'services.mia.property_group_id' => $group->id,
            'services.mia.property_group_name' => null,
        ]);
    }

    /** @return array{PropertyGroup, Lease} */
    private function lease(string $groupName, string $suffix): array
    {
        $group = PropertyGroup::create([
            'name' => $groupName,
            'responsible_name' => 'Responsável '.$suffix,
            'phone' => '81999990000',
            'pix_key' => $suffix.'@example.test',
        ]);
        $client = Client::create([
            'name' => 'Cliente '.$suffix,
            'phone' => '81988880000',
            'cpf' => str_pad((string) Client::count(), 11, '1'),
            'email' => $suffix.'@example.test',
            'status' => 'active',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'Imóvel '.$suffix,
            'slug' => 'imovel-'.$suffix,
            'description' => 'Imóvel usado no teste da integração Mia.',
            'type' => 'residential',
            'street' => 'Rua do Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 900,
            'status' => 'rented',
            'has_solar_energy' => true,
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-01-01',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 900,
            'status' => 'active',
            'has_solar_energy' => true,
        ]);

        return [$group, $lease];
    }

    private function charge(Lease $lease, string $type, string $month, string $amount): Charge
    {
        return Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $lease->client_id,
            'type' => $type,
            'reference_month' => $month,
            'due_date' => Carbon::parse($month)->day(10),
            'amount' => $amount,
            'status' => 'open',
        ]);
    }

    private function pendingReceipt(): MiaReceipt
    {
        config([
            'services.mia.url' => 'https://mia.example',
            'services.mia.token' => 'mia-secret',
            'services.mia.connect_timeout' => 1,
            'services.mia.timeout' => 2,
        ]);

        return MiaReceipt::create([
            'charge_id' => $this->charge($this->lease('Melo Jr', 'job')[1], 'rent', '2026-08-01', '1850.00')->id,
            'mia_client_id' => 42,
            'external_id' => 'alugapro:charge:999',
            'payload' => [
                'external_id' => 'alugapro:charge:999',
                'title' => 'Aluguel recebido',
                'description' => 'Pagamento da competência 08/2026',
                'amount' => '1850.00',
                'occurred_on' => '2026-08-30',
            ],
            'status' => MiaReceipt::STATUS_PENDING,
        ]);
    }
}

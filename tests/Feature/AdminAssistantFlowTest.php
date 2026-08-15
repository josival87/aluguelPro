<?php

namespace Tests\Feature;

use App\Models\AdminAiAction;
use App\Models\AdminAiConversation;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAssistantFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-14 10:00:00');
        config(['services.openai.api_key' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_natural_language_command_settles_the_unique_charge_locally(): void
    {
        [$admin, $lease] = $this->fixture();
        $charge = $this->charge($lease, 'rent');

        $response = $this->actingAs($admin)->post(route('admin.assistant.messages'), [
            'prompt' => 'dar baixa no pagamento EBM 02 do mês de setembro',
        ]);

        $conversation = AdminAiConversation::firstOrFail();
        $response->assertRedirect(route('admin.assistant.index', $conversation).'#ultima-mensagem');
        $charge->refresh();
        $this->assertSame('paid', $charge->status);
        $this->assertSame('ai_agent', $charge->payment_method);
        $this->assertNotNull($charge->paid_at);
        $this->assertDatabaseHas('admin_ai_actions', [
            'conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'action' => 'settle_charge',
            'target_id' => $charge->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('admin_ai_messages', ['role' => 'assistant']);
    }

    public function test_ambiguous_charge_does_not_change_any_payment(): void
    {
        [$admin, $lease] = $this->fixture();
        $rent = $this->charge($lease, 'rent');
        $solar = $this->charge($lease, 'solar', 185.40);

        $this->actingAs($admin)->post(route('admin.assistant.messages'), [
            'prompt' => 'dar baixa no pagamento EBM 02 do mês de setembro',
        ])->assertRedirect();

        $this->assertSame('open', $rent->fresh()->status);
        $this->assertSame('open', $solar->fresh()->status);
        $this->assertDatabaseHas('admin_ai_actions', [
            'action' => 'settle_charge',
            'target_id' => null,
            'status' => 'needs_clarification',
        ]);
        $this->assertStringContainsString('não alterei nada', AdminAiConversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail()->content);
    }

    public function test_explicit_charge_type_changes_only_that_charge(): void
    {
        [$admin, $lease] = $this->fixture();
        $rent = $this->charge($lease, 'rent');
        $solar = $this->charge($lease, 'solar', 185.40);

        $this->actingAs($admin)->post(route('admin.assistant.messages'), [
            'prompt' => 'dar baixa no pagamento de energia solar EBM 02 do mês de setembro',
        ])->assertRedirect();

        $this->assertSame('open', $rent->fresh()->status);
        $this->assertSame('paid', $solar->fresh()->status);
        $this->assertSame('ai_agent', $solar->fresh()->payment_method);
    }

    public function test_openai_function_call_uses_the_same_audited_tool(): void
    {
        [$admin, $lease] = $this->fixture();
        $charge = $this->charge($lease, 'rent');
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.url' => 'https://api.openai.test/v1/responses',
        ]);
        Http::fakeSequence()
            ->push(['output' => [[
                'type' => 'function_call',
                'name' => 'settle_charge',
                'call_id' => 'call_123',
                'arguments' => json_encode([
                    'property_title' => 'EBM 02',
                    'month' => 9,
                    'year' => 2026,
                    'charge_type' => 'rent',
                ]),
            ]]])
            ->push(['output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'Pagamento confirmado com sucesso.']],
            ]]]);

        $this->actingAs($admin)->post(route('admin.assistant.messages'), [
            'prompt' => 'Dê baixa no aluguel EBM 02 de setembro.',
        ])->assertRedirect();

        $this->assertSame('paid', $charge->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.test/v1/responses'
            && $request['store'] === false
            && count($request['tools']) === 4);
        $assistantMessage = AdminAiConversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('openai', $assistantMessage->metadata['provider']);
        $this->assertSame('Pagamento confirmado com sucesso.', $assistantMessage->content);
    }

    public function test_each_admin_can_only_see_their_own_conversations(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = AdminAiConversation::create([
            'user_id' => $owner->id,
            'title' => 'Privada',
            'last_message_at' => now(),
        ]);

        $this->actingAs($other)->get(route('admin.assistant.index', $conversation))->assertNotFound();
        $this->actingAs($other)->post(route('admin.assistant.messages'), [
            'conversation_id' => $conversation->id,
            'prompt' => 'Liste as cobranças deste mês',
        ])->assertNotFound();
    }

    /** @return array{User, Lease} */
    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = PropertyGroup::create([
            'name' => 'Edifício Boa Morada',
            'responsible_name' => 'Responsável',
            'phone' => '81999999999',
            'pix_key' => '81999999999',
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'title' => 'EBM 02',
            'slug' => 'ebm-02',
            'description' => 'Imóvel de teste',
            'type' => 'residential',
            'street' => 'Rua de Teste',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'rent_amount' => 1200,
            'status' => 'rented',
        ]);
        $client = Client::create([
            'name' => 'Locatário de Teste',
            'phone' => '81988888888',
            'cpf' => '123.456.789-00',
            'email' => 'locatario@example.test',
            'status' => 'active',
        ]);
        $lease = Lease::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'contract_months' => 12,
            'due_day' => 10,
            'rent_amount' => 1200,
            'status' => 'active',
        ]);

        return [$admin, $lease];
    }

    private function charge(Lease $lease, string $type, float $amount = 1200): Charge
    {
        return Charge::create([
            'lease_id' => $lease->id,
            'client_id' => $lease->client_id,
            'type' => $type,
            'reference_month' => '2026-09-01',
            'due_date' => '2026-09-10',
            'amount' => $amount,
            'status' => 'open',
        ]);
    }
}

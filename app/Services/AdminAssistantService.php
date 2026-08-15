<?php

namespace App\Services;

use App\Models\AdminAiConversation;
use App\Models\AdminAiMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAssistantService
{
    public function __construct(
        private readonly LocalAdminCommandParser $parser,
        private readonly AdminAssistantTools $tools,
    ) {
    }

    /** @return array{message: string, provider: string, action_ids: array<int>} */
    public function respond(AdminAiConversation $conversation, User $user, AdminAiMessage $message): array
    {
        if (blank(config('services.openai.api_key'))) {
            return $this->respondLocally($conversation, $user, $message);
        }

        $toolResult = null;
        try {
            $input = $conversation->messages()->latest('id')->limit(12)->get()->reverse()->values()
                ->map(fn (AdminAiMessage $item) => ['role' => $item->role, 'content' => $item->content])->all();
            $response = $this->send($this->baseRequest($user) + ['input' => $input]);
            $output = $response['output'] ?? [];
            $functionCall = collect($output)->firstWhere('type', 'function_call');

            if ($functionCall) {
                $arguments = json_decode($functionCall['arguments'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($arguments)) {
                    throw new \RuntimeException('Argumentos de ferramenta inválidos.');
                }
                $toolResult = $this->tools->execute(
                    (string) ($functionCall['name'] ?? ''), $arguments, $conversation, $user, $message
                );
                $finalResponse = $this->send($this->baseRequest($user) + ['input' => array_merge($input, $output, [[
                    'type' => 'function_call_output',
                    'call_id' => $functionCall['call_id'],
                    'output' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]])]);
                return [
                    'message' => $this->extractText($finalResponse) ?: $toolResult['message'],
                    'provider' => 'openai',
                    'action_ids' => [$toolResult['action_id']],
                ];
            }

            $text = $this->extractText($response);
            if ($text === null) {
                throw new \RuntimeException('A resposta do modelo veio vazia.');
            }
            return ['message' => $text, 'provider' => 'openai', 'action_ids' => []];
        } catch (Throwable $exception) {
            Log::warning('Falha no provedor do assistente administrativo.', [
                'conversation_id' => $conversation->id,
                'exception' => $exception->getMessage(),
            ]);
            if ($toolResult !== null) {
                return ['message' => $toolResult['message'], 'provider' => 'local_fallback', 'action_ids' => [$toolResult['action_id']]];
            }
            return $this->respondLocally($conversation, $user, $message, true);
        }
    }

    /** @return array{message: string, provider: string, action_ids: array<int>} */
    private function respondLocally(AdminAiConversation $conversation, User $user, AdminAiMessage $message, bool $fallback = false): array
    {
        $command = $this->parser->parse($message->content);
        if ($command === null) {
            $prefix = $fallback ? 'A inteligência online está indisponível no momento. ' : '';
            return [
                'message' => $prefix.'Posso dar baixa ou reabrir uma cobrança, listar cobranças e montar um resumo financeiro mensal. Inclua o título do imóvel e o mês quando quiser alterar um pagamento.',
                'provider' => $fallback ? 'local_fallback' : 'local',
                'action_ids' => [],
            ];
        }
        $result = $this->tools->execute($command['tool'], $command['arguments'], $conversation, $user, $message);
        return [
            'message' => $result['message'],
            'provider' => $fallback ? 'local_fallback' : 'local',
            'action_ids' => [$result['action_id']],
        ];
    }

    /** @return array<string, mixed> */
    private function baseRequest(User $user): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-5.6-luna'),
            'instructions' => $this->instructions(),
            'tools' => $this->toolDefinitions(),
            'parallel_tool_calls' => false,
            'store' => false,
            'safety_identifier' => hash('sha256', 'alugapro-admin-'.$user->id),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function send(array $payload): array
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->acceptJson()->timeout((int) config('services.openai.timeout', 30))
            ->post((string) config('services.openai.url'), $payload);
        $response->throw();
        return $response->json();
    }

    /** @param array<string, mixed> $response */
    private function extractText(array $response): ?string
    {
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    return trim($content['text']);
                }
            }
        }
        return null;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Você é o assistente administrativo do AlugaPro. Responda sempre em português do Brasil, de forma breve e precisa.
Use as ferramentas para consultar qualquer dado financeiro do sistema; nunca invente imóveis, cobranças, valores ou situações.
Só chame settle_charge quando o usuário pedir explicitamente para dar baixa, registrar ou confirmar um pagamento.
Só chame reopen_charge quando o usuário pedir explicitamente para reabrir, estornar ou desfazer uma baixa.
Quando faltar imóvel, mês ou outra informação essencial, peça o dado sem executar uma alteração.
As ferramentas recusam resultados ambíguos. Explique claramente a opção que o usuário deve especificar.
O ano padrão, quando não informado, é o ano atual. Não prometa operações que não existam na lista de ferramentas.
PROMPT;
    }

    /** @return array<int, array<string, mixed>> */
    private function toolDefinitions(): array
    {
        $changeProperties = [
            'property_title' => ['type' => 'string', 'description' => 'Título cadastrado do imóvel ou aluguel.'],
            'month' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
            'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
            'charge_type' => ['anyOf' => [['type' => 'string', 'enum' => ['rent', 'solar']], ['type' => 'null']]],
        ];
        return [
            $this->tool('settle_charge', 'Registra como paga uma única cobrança identificada sem ambiguidade.', $changeProperties),
            $this->tool('reopen_charge', 'Reabre uma única cobrança paga identificada sem ambiguidade.', $changeProperties),
            $this->tool('list_charges', 'Lista cobranças do mês com filtros opcionais.', [
                'month' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                'property_title' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                'charge_type' => ['anyOf' => [['type' => 'string', 'enum' => ['rent', 'solar']], ['type' => 'null']]],
                'status' => ['anyOf' => [['type' => 'string', 'enum' => ['open', 'paid', 'overdue']], ['type' => 'null']]],
            ]),
            $this->tool('financial_summary', 'Calcula totais de cobranças, recebidos e valores em aberto no mês.', [
                'month' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                'property_title' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
            ]),
        ];
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private function tool(string $name, string $description, array $properties): array
    {
        return [
            'type' => 'function', 'name' => $name, 'description' => $description,
            'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false],
            'strict' => true,
        ];
    }
}

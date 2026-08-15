<?php

namespace App\Services;

use App\Models\AdminAiAction;
use App\Models\AdminAiConversation;
use App\Models\AdminAiMessage;
use App\Models\Charge;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AdminAssistantTools
{
    public function __construct(private readonly ChargePaymentService $payments)
    {
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function execute(string $tool, array $arguments, AdminAiConversation $conversation, User $user, AdminAiMessage $message): array
    {
        return match ($tool) {
            'settle_charge', 'reopen_charge' => $this->changeCharge($tool, $arguments, $conversation, $user, $message),
            'list_charges' => $this->listCharges($arguments, $conversation, $user, $message),
            'financial_summary' => $this->financialSummary($arguments, $conversation, $user, $message),
            default => $this->recordResult($tool, $arguments, $conversation, $user, $message, 'rejected', 'Esse comando não faz parte das operações permitidas do assistente.'),
        };
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function changeCharge(string $tool, array $arguments, AdminAiConversation $conversation, User $user, AdminAiMessage $message): array
    {
        $period = $this->period($arguments);
        if ($period === null || empty($arguments['property_title'])) {
            return $this->recordResult($tool, $arguments, $conversation, $user, $message, 'needs_clarification', 'Informe o imóvel e o mês da cobrança.');
        }

        $propertyResult = $this->resolveProperty((string) $arguments['property_title']);
        if ($propertyResult['property'] === null) {
            return $this->recordResult($tool, $arguments, $conversation, $user, $message, $propertyResult['status'], $propertyResult['message']);
        }

        $query = $this->chargesForPeriod($period['month'], $period['year'])
            ->whereHas('lease', fn (Builder $query) => $query->where('property_id', $propertyResult['property']->id));
        if (in_array($arguments['charge_type'] ?? null, ['rent', 'solar'], true)) {
            $query->where('type', $arguments['charge_type']);
        }
        $charges = $query->orderBy('id')->get();

        if ($charges->isEmpty()) {
            return $this->recordResult(
                $tool, $arguments, $conversation, $user, $message, 'not_found',
                'Não encontrei uma cobrança para '.$propertyResult['property']->title.' em '.$this->periodLabel($period['month'], $period['year']).'.'
            );
        }

        if ($charges->count() > 1) {
            $options = $charges->map(fn (Charge $charge) => $this->chargeLabel($charge))->implode('; ');
            return $this->recordResult(
                $tool, $arguments, $conversation, $user, $message, 'needs_clarification',
                'Encontrei mais de uma cobrança e não alterei nada. Especifique se é aluguel ou energia solar: '.$options.'.'
            );
        }

        /** @var Charge $charge */
        $charge = $charges->first();
        $result = $tool === 'settle_charge' ? $this->payments->settle($charge, 'ai_agent') : $this->payments->reopen($charge);
        $updated = $result['charge']->loadMissing('lease.property', 'client');
        $verb = $tool === 'settle_charge' ? 'Pagamento registrado' : 'Cobrança reaberta';
        $already = $tool === 'settle_charge' ? 'Essa cobrança já estava paga' : 'Essa cobrança já estava em aberto';

        return $this->recordResult(
            $tool, $arguments, $conversation, $user, $message, $result['changed'] ? 'completed' : 'no_op',
            ($result['changed'] ? $verb : $already).': '.$this->chargeLabel($updated).'.', $updated
        );
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function listCharges(array $arguments, AdminAiConversation $conversation, User $user, AdminAiMessage $message): array
    {
        $period = $this->period($arguments);
        if ($period === null) {
            return $this->recordResult('list_charges', $arguments, $conversation, $user, $message, 'needs_clarification', 'Informe o mês que deseja consultar.');
        }

        $query = $this->chargesForPeriod($period['month'], $period['year']);
        $property = null;
        if (! empty($arguments['property_title'])) {
            $propertyResult = $this->resolveProperty((string) $arguments['property_title']);
            if ($propertyResult['property'] === null) {
                return $this->recordResult('list_charges', $arguments, $conversation, $user, $message, $propertyResult['status'], $propertyResult['message']);
            }
            $property = $propertyResult['property'];
            $query->whereHas('lease', fn (Builder $query) => $query->where('property_id', $property->id));
        }
        if (in_array($arguments['charge_type'] ?? null, ['rent', 'solar'], true)) {
            $query->where('type', $arguments['charge_type']);
        }

        $status = $arguments['status'] ?? 'open';
        if ($status === 'overdue') {
            $query->where('status', 'open')->whereDate('due_date', '<', now()->toDateString());
        } elseif (in_array($status, ['open', 'paid'], true)) {
            $query->where('status', $status);
        }

        $charges = $query->orderBy('due_date')->limit(20)->get();
        $scope = $property ? ' de '.$property->title : '';
        if ($charges->isEmpty()) {
            return $this->recordResult('list_charges', $arguments, $conversation, $user, $message, 'completed', 'Não há cobranças com esses filtros'.$scope.' em '.$this->periodLabel($period['month'], $period['year']).'.');
        }

        $items = $charges->map(fn (Charge $charge) => '• '.$this->chargeLabel($charge))->implode("\n");
        return $this->recordResult(
            'list_charges', $arguments, $conversation, $user, $message, 'completed',
            'Encontrei '.$charges->count().' cobrança(s)'.$scope.' em '.$this->periodLabel($period['month'], $period['year']).":\n".$items,
            resultData: ['charge_ids' => $charges->pluck('id')->all()]
        );
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function financialSummary(array $arguments, AdminAiConversation $conversation, User $user, AdminAiMessage $message): array
    {
        $period = $this->period($arguments);
        if ($period === null) {
            return $this->recordResult('financial_summary', $arguments, $conversation, $user, $message, 'needs_clarification', 'Informe o mês que deseja resumir.');
        }

        $query = $this->chargesForPeriod($period['month'], $period['year']);
        $scope = '';
        if (! empty($arguments['property_title'])) {
            $propertyResult = $this->resolveProperty((string) $arguments['property_title']);
            if ($propertyResult['property'] === null) {
                return $this->recordResult('financial_summary', $arguments, $conversation, $user, $message, $propertyResult['status'], $propertyResult['message']);
            }
            $scope = ' de '.$propertyResult['property']->title;
            $query->whereHas('lease', fn (Builder $query) => $query->where('property_id', $propertyResult['property']->id));
        }

        $total = (float) (clone $query)->sum('amount');
        $received = (float) (clone $query)->where('status', 'paid')->sum('amount');
        $open = (float) (clone $query)->where('status', 'open')->sum('amount');
        $count = (clone $query)->count();
        $data = compact('total', 'received', 'open', 'count');
        $text = 'Resumo'.$scope.' de '.$this->periodLabel($period['month'], $period['year']).': '
            .$count.' cobrança(s), total '.$this->money($total).', recebido '.$this->money($received).' e em aberto '.$this->money($open).'.';
        return $this->recordResult('financial_summary', $arguments, $conversation, $user, $message, 'completed', $text, resultData: $data);
    }

    /** @return array{property: ?Property, status: string, message: string} */
    private function resolveProperty(string $requestedTitle): array
    {
        $requested = $this->normalize($requestedTitle);
        $properties = Property::query()->get(['id', 'title']);
        $exact = $properties->filter(fn (Property $property) => $this->normalize($property->title) === $requested)->values();
        if ($exact->count() === 1) {
            return ['property' => $exact->first(), 'status' => 'completed', 'message' => ''];
        }
        $partial = $properties->filter(function (Property $property) use ($requested) {
            $title = $this->normalize($property->title);
            return $requested !== '' && (str_contains($title, $requested) || str_contains($requested, $title));
        })->values();
        if ($partial->count() === 1) {
            return ['property' => $partial->first(), 'status' => 'completed', 'message' => ''];
        }
        if ($exact->count() > 1 || $partial->count() > 1) {
            $titles = ($exact->isNotEmpty() ? $exact : $partial)->pluck('title')->implode(', ');
            return ['property' => null, 'status' => 'needs_clarification', 'message' => 'Há mais de um imóvel correspondente: '.$titles.'. Informe o título completo.'];
        }
        return ['property' => null, 'status' => 'not_found', 'message' => 'Não encontrei o imóvel “'.$requestedTitle.'”. Confira o título cadastrado.'];
    }

    private function chargesForPeriod(int $month, int $year): Builder
    {
        return Charge::query()->with('lease.property', 'client')->whereMonth('reference_month', $month)->whereYear('reference_month', $year);
    }

    /** @param array<string, mixed> $arguments @return array{month: int, year: int}|null */
    private function period(array $arguments): ?array
    {
        $month = filter_var($arguments['month'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($arguments['year'] ?? null, FILTER_VALIDATE_INT);
        if ($month === false || $month < 1 || $month > 12 || $year === false || $year < 2000 || $year > 2100) {
            return null;
        }
        return ['month' => $month, 'year' => $year];
    }

    private function chargeLabel(Charge $charge): string
    {
        $charge->loadMissing('lease.property', 'client');
        $type = $charge->type === 'solar' ? 'energia solar' : 'aluguel';
        $status = $charge->status === 'paid' ? 'paga' : ($charge->due_date->isPast() ? 'vencida' : 'em aberto');
        return $charge->lease->property->title.' — '.$type.' — '.$this->money((float) $charge->amount).' — '.$status.' — vencimento '.$charge->due_date->format('d/m/Y');
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($value))));
    }

    private function periodLabel(int $month, int $year): string
    {
        return Carbon::create($year, $month, 1)->locale('pt_BR')->translatedFormat('F \d\e Y');
    }

    private function money(float $amount): string
    {
        return 'R$ '.number_format($amount, 2, ',', '.');
    }

    /** @param array<string, mixed> $parameters @param array<string, mixed> $resultData @return array<string, mixed> */
    private function recordResult(
        string $tool,
        array $parameters,
        AdminAiConversation $conversation,
        User $user,
        AdminAiMessage $message,
        string $status,
        string $text,
        ?Charge $target = null,
        array $resultData = [],
    ): array {
        $result = ['message' => $text] + $resultData;
        $action = AdminAiAction::query()->create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'action' => $tool,
            'parameters' => $parameters,
            'target_type' => $target ? Charge::class : null,
            'target_id' => $target?->id,
            'status' => $status,
            'result' => $result,
            'executed_at' => in_array($status, ['completed', 'no_op'], true) ? now() : null,
        ]);
        return ['ok' => in_array($status, ['completed', 'no_op'], true), 'status' => $status, 'message' => $text, 'data' => $resultData, 'action_id' => $action->id];
    }
}

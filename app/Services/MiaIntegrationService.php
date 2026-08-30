<?php

namespace App\Services;

use App\Jobs\SendMiaReceipt;
use App\Models\Charge;
use App\Models\MiaReceipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class MiaIntegrationService
{
    public function record(Charge $charge): ?MiaReceipt
    {
        if (! config('services.mia.enabled', false) || ! in_array($charge->type, ['rent', 'solar'], true)) {
            return null;
        }

        $charge->loadMissing('lease.property.group', 'client');

        if (! $this->isTargetGroup($charge)) {
            return null;
        }

        $this->assertConfigured();

        if ($charge->status !== 'paid' || (float) $charge->amount <= 0 || $charge->payment_method === 'waiver') {
            return null;
        }

        $payload = $this->payload($charge);

        return MiaReceipt::firstOrCreate(
            ['charge_id' => $charge->getKey()],
            [
                'mia_client_id' => (int) config('services.mia.client_id'),
                'external_id' => $payload['external_id'],
                'payload' => $payload,
                'status' => MiaReceipt::STATUS_PENDING,
            ],
        );
    }

    public function dispatch(?MiaReceipt $receipt): void
    {
        if (! $receipt || $receipt->status !== MiaReceipt::STATUS_PENDING) {
            return;
        }

        try {
            SendMiaReceipt::dispatch($receipt->getKey());
        } catch (Throwable $exception) {
            Log::error('Não foi possível enfileirar um recebimento para a Mia.', [
                'mia_receipt_id' => $receipt->getKey(),
                'charge_id' => $receipt->charge_id,
                'exception' => $exception::class,
            ]);
        }
    }

    /** @return array{external_id: string, title: string, description: string, amount: string, occurred_on: string} */
    private function payload(Charge $charge): array
    {
        $isSolar = $charge->type === 'solar';
        $type = $isSolar ? 'Energia solar' : 'Aluguel';
        $reference = $charge->reference_month->format('m/Y');
        $property = $charge->lease->property->title;
        $client = $charge->client->name;
        $description = "{$type} de {$property}, competência {$reference}, locatário {$client}.";
        $occurredOn = $charge->paid_at
            ->copy()
            ->setTimezone(config('business.billing_timezone', 'America/Sao_Paulo'))
            ->toDateString();

        return [
            'external_id' => 'alugapro:charge:'.$charge->getKey(),
            'title' => $isSolar ? 'Energia solar recebida' : 'Aluguel recebido',
            'description' => Str::limit($description, 255, ''),
            'amount' => number_format((float) $charge->amount, 2, '.', ''),
            'occurred_on' => $occurredOn,
        ];
    }

    private function isTargetGroup(Charge $charge): bool
    {
        $group = $charge->lease->property->group;
        $configuredId = config('services.mia.property_group_id');

        if ($configuredId !== null && $configuredId !== '') {
            return $group->getKey() === (int) $configuredId;
        }

        return $this->normalizeGroupName($group->name)
            === $this->normalizeGroupName((string) config('services.mia.property_group_name'));
    }

    private function normalizeGroupName(string $name): string
    {
        $normalized = Str::lower(trim($name));

        return preg_replace('/^grupo\s+/u', '', $normalized) ?? $normalized;
    }

    private function assertConfigured(): void
    {
        $clientId = config('services.mia.client_id');
        $hasGroup = filled(config('services.mia.property_group_id'))
            || filled(config('services.mia.property_group_name'));

        if (! filled(config('services.mia.url'))
            || ! filled(config('services.mia.token'))
            || ! is_numeric($clientId)
            || (int) $clientId < 1
            || ! $hasGroup) {
            throw new LogicException('A integração com a Mia está habilitada, mas sua configuração está incompleta.');
        }
    }
}

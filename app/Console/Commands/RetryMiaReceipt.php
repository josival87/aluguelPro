<?php

namespace App\Console\Commands;

use App\Models\MiaReceipt;
use App\Services\MiaIntegrationService;
use Illuminate\Console\Command;

class RetryMiaReceipt extends Command
{
    protected $signature = 'mia:retry-receipt {receipt : ID local do registro em mia_receipts}';

    protected $description = 'Reenvia um recebimento da Mia após a causa de uma falha ser corrigida';

    public function handle(MiaIntegrationService $integration): int
    {
        if (! config('services.mia.enabled', false)) {
            $this->error('A integração com a Mia está desabilitada.');

            return self::FAILURE;
        }

        $clientId = config('services.mia.client_id');
        if (! filled(config('services.mia.url'))
            || ! filled(config('services.mia.token'))
            || ! is_numeric($clientId)
            || (int) $clientId < 1) {
            $this->error('A configuração da Mia está incompleta.');

            return self::FAILURE;
        }

        $receipt = MiaReceipt::find($this->argument('receipt'));
        if (! $receipt) {
            $this->error('Recebimento local não encontrado.');

            return self::FAILURE;
        }

        if ($receipt->status === MiaReceipt::STATUS_SENT) {
            $this->warn('Esse recebimento já foi confirmado pela Mia.');

            return self::INVALID;
        }

        $receipt->update([
            'mia_client_id' => (int) $clientId,
            'status' => MiaReceipt::STATUS_PENDING,
            'last_error' => null,
        ]);
        $integration->dispatch($receipt->fresh());
        $this->info('Recebimento reenfileirado com o mesmo external_id e o mesmo conteúdo.');

        return self::SUCCESS;
    }
}

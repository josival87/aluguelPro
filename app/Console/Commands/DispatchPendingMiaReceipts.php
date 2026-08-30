<?php

namespace App\Console\Commands;

use App\Models\MiaReceipt;
use App\Services\MiaIntegrationService;
use Illuminate\Console\Command;

class DispatchPendingMiaReceipts extends Command
{
    protected $signature = 'mia:dispatch-pending';

    protected $description = 'Reenfileira recebimentos pendentes para a Mia';

    public function handle(MiaIntegrationService $integration): int
    {
        if (! config('services.mia.enabled', false)) {
            $this->info('A integração com a Mia está desabilitada.');

            return self::SUCCESS;
        }

        $count = 0;
        MiaReceipt::query()
            ->where('status', MiaReceipt::STATUS_PENDING)
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (MiaReceipt $receipt) use ($integration, &$count): void {
                $integration->dispatch($receipt);
                $count++;
            });

        $this->info($count.' recebimento(s) pendente(s) enfileirado(s).');

        return self::SUCCESS;
    }
}

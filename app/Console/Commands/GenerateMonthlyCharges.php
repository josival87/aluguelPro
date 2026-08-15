<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyCharges extends Command
{
    protected $signature = 'billing:generate {month? : Mês no formato YYYY-MM}';
    protected $description = 'Gera cobranças mensais de aluguel para contratos ativos';

    public function handle(BillingService $billing): int
    {
        $month = $this->argument('month') ? Carbon::createFromFormat('Y-m', $this->argument('month'))->startOfMonth() : now()->startOfMonth();
        $this->info($billing->generateMonth($month).' cobrança(s) criada(s).');
        return self::SUCCESS;
    }
}

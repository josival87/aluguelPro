<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyCharges extends Command
{
    protected $signature = 'billing:generate
                            {month? : Mês no formato YYYY-MM}
                            {--next : Gera as cobranças do próximo mês}';

    protected $description = 'Gera cobranças mensais de aluguel para contratos ativos';

    public function handle(BillingService $billing): int
    {
        if ($this->argument('month') && $this->option('next')) {
            $this->error('Informe um mês ou use --next, não os dois.');

            return self::INVALID;
        }

        $month = $this->option('next')
            ? Carbon::now(config('business.billing_timezone', 'America/Sao_Paulo'))->addMonthNoOverflow()->startOfMonth()
            : ($this->argument('month')
                ? Carbon::createFromFormat('Y-m', $this->argument('month'))->startOfMonth()
                : now()->startOfMonth());

        $this->info($billing->generateMonth($month).' cobrança(s) criada(s).');

        return self::SUCCESS;
    }
}

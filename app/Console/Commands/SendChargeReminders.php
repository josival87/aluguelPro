<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\WhatsAppAutomation;
use App\Services\MoneyCalculator;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendChargeReminders extends Command
{
    protected $signature = 'billing:remind';

    protected $description = 'Envia lembretes antes, no vencimento e a cada três dias de atraso';

    public function handle(WhatsAppService $whatsApp, MoneyCalculator $calculator): int
    {
        $automations = WhatsAppAutomation::configured()->keyBy('key');
        $today = now(config('business.billing_timezone', 'America/Sao_Paulo'))->startOfDay();

        Charge::query()->where('status', 'open')
            ->where(function ($query) use ($today): void {
                $query->whereDate('due_date', $today)
                    ->orWhereDate('due_date', $today->copy()->addDays(5))
                    ->orWhereDate('due_date', '<', $today);
            })
            ->with('client', 'lease.property.group')->chunkById(100, function ($charges) use ($automations, $whatsApp, $calculator, $today) {
                foreach ($charges as $charge) {
                    $dueDate = $charge->due_date->toDateString();
                    $todayDate = $today->toDateString();

                    if ($dueDate < $todayDate) {
                        $daysLate = $calculator->payable($charge, $today)['days_late'];
                        if ($daysLate % 3 === 0 && ! $this->wasSentToday($charge, WhatsAppAutomation::OVERDUE)) {
                            $whatsApp->send(
                                $charge->client->phone,
                                $automations->get(WhatsAppAutomation::OVERDUE)->render($charge),
                                WhatsAppAutomation::OVERDUE,
                                'client',
                                $charge,
                            );
                        }

                        continue;
                    }

                    $clientEvent = $dueDate === $todayDate
                        ? WhatsAppAutomation::DUE_TODAY
                        : WhatsAppAutomation::DUE_IN_5_DAYS;

                    if (! $this->wasSentToday($charge, $clientEvent)) {
                        $whatsApp->send(
                            $charge->client->phone,
                            $automations->get($clientEvent)->render($charge),
                            $clientEvent,
                            'client',
                            $charge,
                        );
                    }

                    if ($dueDate === $todayDate) {
                        $groupEvent = WhatsAppAutomation::GROUP_DUE_TODAY;
                        if (! $this->wasSentToday($charge, $groupEvent)) {
                            $whatsApp->send(
                                $charge->lease->property->group->phone,
                                $automations->get($groupEvent)->render($charge),
                                $groupEvent,
                                'responsible',
                                $charge,
                            );
                        }
                    }
                }
            });

        return self::SUCCESS;
    }

    private function wasSentToday(Charge $charge, string $event): bool
    {
        return $charge->notificationLogs()
            ->where('event', $event)
            ->whereDate('created_at', today())
            ->exists();
    }
}

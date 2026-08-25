<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\WhatsAppAutomation;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendChargeReminders extends Command
{
    protected $signature = 'billing:remind';

    protected $description = 'Envia lembretes de cobranças cinco dias antes e no vencimento';

    public function handle(WhatsAppService $whatsApp): int
    {
        $automations = WhatsAppAutomation::configured()->keyBy('key');

        Charge::query()->where('status', 'open')
            ->where(function ($query): void {
                $query->whereDate('due_date', today())
                    ->orWhereDate('due_date', today()->addDays(5));
            })
            ->with('client', 'lease.property.group')->chunkById(100, function ($charges) use ($automations, $whatsApp) {
                foreach ($charges as $charge) {
                    $clientEvent = $charge->due_date->isToday()
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

                    if ($charge->due_date->isToday()) {
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

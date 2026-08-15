<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendChargeReminders extends Command
{
    protected $signature = 'billing:remind';
    protected $description = 'Envia lembretes de cobranças cinco dias antes e no vencimento';

    public function handle(WhatsAppService $whatsApp): int
    {
        Charge::query()->where('status', 'open')->whereIn('due_date', [today()->toDateString(), today()->addDays(5)->toDateString()])
            ->with('client', 'lease.property.group')->chunkById(100, function ($charges) use ($whatsApp) {
                foreach ($charges as $charge) {
                    $event = $charge->due_date->isToday() ? 'due_today' : 'due_in_5_days';
                    if ($charge->notificationLogs()->where('event', $event)->whereDate('created_at', today())->exists()) continue;
                    $amount = 'R$ '.number_format((float) $charge->amount, 2, ',', '.');
                    $message = "AlugaPro: sua cobrança de {$amount} vence em ".$charge->due_date->format('d/m/Y').'.';
                    $whatsApp->send($charge->client->phone, $message, $event, 'client', $charge);
                    if ($charge->due_date->isToday()) {
                        $whatsApp->send($charge->lease->property->group->phone, "Vencimento hoje: {$charge->lease->property->title}, {$amount}.", 'responsible_due_today', 'responsible', $charge);
                    }
                }
            });
        return self::SUCCESS;
    }
}

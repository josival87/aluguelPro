<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppService
{
    public function send(string $phone, string $message, string $event, string $recipientType, ?Charge $charge = null): NotificationLog
    {
        $log = NotificationLog::create([
            'charge_id' => $charge?->id,
            'recipient' => $phone,
            'recipient_type' => $recipientType,
            'event' => $event,
            'message' => $message,
            'status' => 'queued',
        ]);

        if (! config('services.whatsapp.url') || ! config('services.whatsapp.token')) {
            $log->update(['status' => 'simulated', 'sent_at' => now()]);
            return $log;
        }

        try {
            $response = Http::withToken(config('services.whatsapp.token'))->post(config('services.whatsapp.url'), [
                'from' => config('services.whatsapp.sender'),
                'to' => $phone,
                'message' => $message,
            ]);
            $response->throw();
            $log->update([
                'status' => 'sent',
                'provider_reference' => $response->json('id'),
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);
        }

        return $log->refresh();
    }
}

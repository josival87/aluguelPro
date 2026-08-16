<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\NotificationLog;
use Throwable;

class WhatsAppService
{
    public function __construct(private readonly WppConnectClient $client) {}

    public function send(string $phone, string $message, string $event, string $recipientType, ?Charge $charge = null): NotificationLog
    {
        return $this->sendText($phone, $message, $event, $recipientType, $charge);
    }

    public function sendText(
        string $phone,
        string $message,
        string $event,
        string $recipientType,
        ?Charge $charge = null,
    ): NotificationLog {
        $log = NotificationLog::create([
            'charge_id' => $charge?->id,
            'recipient' => $phone,
            'recipient_type' => $recipientType,
            'event' => $event,
            'message' => $message,
            'status' => 'queued',
        ]);

        if (! $this->client->setting()->exists || ! $this->client->setting()->isConfigured()) {
            $log->update(['status' => 'simulated', 'sent_at' => now()]);

            return $log;
        }

        try {
            $response = $this->client->sendText($phone, $message);
            $log->update([
                'status' => 'sent',
                'provider_reference' => $this->client->providerReference($response),
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);
        }

        return $log->refresh();
    }

    public function sendImage(
        string $phone,
        string $contents,
        string $filename,
        string $caption,
        string $event,
        string $recipientType,
        ?Charge $charge = null,
    ): NotificationLog {
        $log = NotificationLog::create([
            'charge_id' => $charge?->id,
            'recipient' => $phone,
            'recipient_type' => $recipientType,
            'event' => $event,
            'message' => $caption !== '' ? $caption : "Imagem: {$filename}",
            'status' => 'queued',
        ]);

        if (! $this->client->setting()->exists || ! $this->client->setting()->isConfigured()) {
            $log->update(['status' => 'simulated', 'sent_at' => now()]);

            return $log;
        }

        try {
            $response = $this->client->sendImage($phone, $contents, $filename, $caption);
            $log->update([
                'status' => 'sent',
                'provider_reference' => $this->client->providerReference($response),
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);
        }

        return $log->refresh();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\WhatsAppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MetaWhatsAppWebhookController extends Controller
{
    private const STATUS_RANK = [
        'queued' => 0,
        'simulated' => 0,
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
        'failed' => 4,
    ];

    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));
        $setting = WhatsAppSetting::current();

        abort_unless(
            $mode === 'subscribe'
            && filled($setting->webhook_verify_token)
            && is_string($token)
            && hash_equals($setting->webhook_verify_token, $token),
            403,
            'Não foi possível validar o webhook da Meta.',
        );

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        $setting = WhatsAppSetting::current();
        abort_unless($setting->hasWebhookSecurity(), 503, 'A segurança do webhook não está configurada.');

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $setting->app_secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Assinatura inválida.');

        $payload = $request->json()->all();
        abort_unless(Arr::get($payload, 'object') === 'whatsapp_business_account', 422, 'Evento inválido.');

        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') !== 'messages') {
                    continue;
                }

                $value = Arr::get($change, 'value', []);
                if ((string) Arr::get($value, 'metadata.phone_number_id') !== $setting->phone_number_id) {
                    continue;
                }

                foreach (Arr::get($value, 'statuses', []) as $status) {
                    if (is_array($status)) {
                        $this->recordStatus($status);
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }

    private function recordStatus(array $event): void
    {
        $reference = Arr::get($event, 'id');
        $status = Arr::get($event, 'status');

        if (! is_string($reference) || ! is_string($status) || ! isset(self::STATUS_RANK[$status])) {
            return;
        }

        $occurredAt = is_numeric(Arr::get($event, 'timestamp'))
            ? CarbonImmutable::createFromTimestampUTC((int) Arr::get($event, 'timestamp'))
            : now();

        NotificationLog::query()
            ->where('provider_reference', $reference)
            ->each(function (NotificationLog $log) use ($event, $status, $occurredAt): void {
                $attributes = [];

                if ($status === 'failed') {
                    $attributes['status'] = 'failed';
                    $attributes['error'] = $this->failureMessage($event);
                } elseif ((self::STATUS_RANK[$log->status] ?? 0) <= self::STATUS_RANK[$status]) {
                    $attributes['status'] = $status;
                    $attributes['error'] = null;
                }

                if ($status === 'sent' && $log->sent_at === null) {
                    $attributes['sent_at'] = $occurredAt;
                }
                if ($status === 'delivered') {
                    $attributes['delivered_at'] = $occurredAt;
                }
                if ($status === 'read') {
                    $attributes['delivered_at'] ??= $log->delivered_at ?: $occurredAt;
                    $attributes['read_at'] = $occurredAt;
                }

                if ($attributes !== []) {
                    $log->update($attributes);
                }
            });
    }

    private function failureMessage(array $event): string
    {
        $error = Arr::get($event, 'errors.0', []);
        $parts = [
            Arr::get($error, 'title'),
            Arr::get($error, 'message'),
            Arr::get($error, 'error_data.details'),
        ];

        $message = collect($parts)
            ->filter(fn (mixed $value): bool => is_scalar($value) && filled((string) $value))
            ->map(fn (mixed $value): string => strip_tags((string) $value))
            ->unique()
            ->implode(' — ');

        return Str::limit($message !== '' ? $message : 'A Meta informou falha na entrega.', 1000);
    }
}

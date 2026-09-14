<?php

namespace App\Services;

use App\Exceptions\MetaWhatsAppException;
use App\Models\WhatsAppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MetaWhatsAppClient
{
    private ?WhatsAppSetting $setting = null;

    public function setting(): WhatsAppSetting
    {
        return $this->setting ??= WhatsAppSetting::current();
    }

    public function verifyConfiguration(bool $subscribeWebhook = true): array
    {
        $this->guardConfigured();

        $phone = $this->request('get', $this->setting()->phone_number_id, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,platform_type',
        ]);
        $account = $this->request('get', $this->setting()->business_account_id, [
            'fields' => 'id,name,timezone_id',
        ]);

        if ((string) Arr::get($phone, 'id') !== $this->setting()->phone_number_id) {
            throw new MetaWhatsAppException('A Meta retornou uma identificação de telefone diferente da configurada.');
        }

        if ((string) Arr::get($account, 'id') !== $this->setting()->business_account_id) {
            throw new MetaWhatsAppException('A Meta retornou uma conta do WhatsApp Business diferente da configurada.');
        }

        if ($subscribeWebhook) {
            $subscription = $this->request('post', $this->setting()->business_account_id.'/subscribed_apps');
            if (Arr::get($subscription, 'success') !== true) {
                throw new MetaWhatsAppException('A Meta não confirmou a assinatura de webhooks para esta conta.');
            }
        }

        $displayPhone = $this->cleanDisplayPhone((string) Arr::get($phone, 'display_phone_number', ''));
        $this->setting()->forceFill([
            'connected_phone' => $displayPhone ?: $this->setting()->connected_phone,
            'connection_status' => 'connected',
            'last_error' => null,
            'last_connected_at' => now(),
            'webhook_subscribed_at' => $subscribeWebhook ? now() : $this->setting()->webhook_subscribed_at,
        ])->save();

        return [
            'connected' => true,
            'status' => 'connected',
            'phone' => $displayPhone,
            'verified_name' => Arr::get($phone, 'verified_name'),
            'quality_rating' => Arr::get($phone, 'quality_rating'),
            'account_name' => Arr::get($account, 'name'),
            'webhook_subscribed' => $subscribeWebhook,
            'message' => $subscribeWebhook
                ? 'Credenciais validadas e conta assinada para receber webhooks.'
                : 'Credenciais da Meta validadas.',
        ];
    }

    public function sendText(string $phone, string $message): array
    {
        if (blank($message)) {
            throw new InvalidArgumentException('A mensagem não pode estar vazia.');
        }

        return $this->request('post', $this->setting()->phone_number_id.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ]);
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        string $languageCode = 'pt_BR',
        array $parameters = [],
    ): array {
        if (! preg_match('/^[a-z0-9_]+$/', $templateName)) {
            throw new InvalidArgumentException('Informe um nome de modelo válido da Meta.');
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        if ($parameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn (mixed $value): array => ['type' => 'text', 'text' => (string) $value],
                    array_values($parameters),
                ),
            ]];
        }

        return $this->request('post', $this->setting()->phone_number_id.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'template',
            'template' => $template,
        ]);
    }

    public function sendImage(
        string $phone,
        string $contents,
        string $filename,
        string $caption = '',
        ?string $mimeType = null,
    ): array {
        if ($contents === '') {
            throw new InvalidArgumentException('A imagem não pode estar vazia.');
        }

        $mimeType ??= $this->detectImageMimeType($contents);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new InvalidArgumentException('A Cloud API aceita imagens JPEG ou PNG.');
        }

        $media = $this->uploadMedia($contents, $filename, $mimeType);
        $mediaId = Arr::get($media, 'id');
        if (! is_scalar($mediaId) || blank((string) $mediaId)) {
            throw new MetaWhatsAppException('A Meta não retornou a identificação da imagem enviada.');
        }

        $image = ['id' => (string) $mediaId];
        if ($caption !== '') {
            $image['caption'] = $caption;
        }

        return $this->request('post', $this->setting()->phone_number_id.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'image',
            'image' => $image,
        ]);
    }

    public function providerReference(array $payload): ?string
    {
        $reference = Arr::get($payload, 'messages.0.id');

        return is_scalar($reference) && filled((string) $reference)
            ? Str::limit((string) $reference, 255, '')
            : null;
    }

    public function normalizePhone(string $phone): string
    {
        $hasExplicitCountryCode = str_starts_with(trim($phone), '+');
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (! $hasExplicitCountryCode && in_array(strlen($normalized), [10, 11], true)) {
            $normalized = '55'.$normalized;
        }

        if (! preg_match('/^[1-9][0-9]{7,14}$/', $normalized)) {
            throw new InvalidArgumentException('Informe o telefone com DDI e DDD.');
        }

        return $normalized;
    }

    private function uploadMedia(string $contents, string $filename, string $mimeType): array
    {
        $this->guardConfigured();

        try {
            $response = $this->pendingRequest()
                ->attach('file', $contents, $filename, ['Content-Type' => $mimeType])
                ->post($this->url($this->setting()->phone_number_id.'/media'), [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);
        } catch (ConnectionException) {
            throw new MetaWhatsAppException('Não foi possível acessar a WhatsApp Cloud API.');
        }

        return $this->responsePayload($response);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $this->guardConfigured();

        try {
            $response = match ($method) {
                'get' => $this->pendingRequest()->get($this->url($path), $data),
                'post' => $this->pendingRequest()->asJson()->post($this->url($path), $data),
                default => throw new InvalidArgumentException("Método HTTP {$method} não suportado."),
            };
        } catch (ConnectionException) {
            throw new MetaWhatsAppException('Não foi possível acessar a WhatsApp Cloud API.');
        }

        return $this->responsePayload($response);
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->setting()->access_token)
            ->connectTimeout((int) config('services.meta_whatsapp.connect_timeout', 5))
            ->timeout((int) config('services.meta_whatsapp.timeout', 30));
    }

    private function responsePayload(Response $response): array
    {
        $json = $response->json();

        if ($response->failed()) {
            $message = Arr::get($json, 'error.message');
            $details = Arr::get($json, 'error.error_data.details');
            $code = Arr::get($json, 'error.code');
            $description = collect([$message, $details])
                ->filter(fn (mixed $value): bool => is_scalar($value) && filled((string) $value))
                ->map(fn (mixed $value): string => Str::limit(strip_tags((string) $value), 250))
                ->unique()
                ->implode(' — ');

            throw new MetaWhatsAppException(
                'A Meta respondeu com HTTP '.$response->status()
                .($code ? " (código {$code})" : '')
                .($description !== '' ? ": {$description}" : '.'),
            );
        }

        if (! is_array($json)) {
            throw new MetaWhatsAppException('A Meta retornou uma resposta inválida.');
        }

        return $json;
    }

    private function guardConfigured(): void
    {
        if (! $this->setting()->isConfigured()) {
            throw new MetaWhatsAppException('Configure as credenciais da WhatsApp Cloud API antes de enviar mensagens.');
        }
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'
            .rawurlencode($this->setting()->graph_api_version)
            .'/'.ltrim($path, '/');
    }

    private function detectImageMimeType(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        return is_array($info) ? ($info['mime'] ?? null) : null;
    }

    private function cleanDisplayPhone(string $phone): ?string
    {
        $phone = trim($phone);

        return $phone !== '' ? Str::limit($phone, 20, '') : null;
    }
}

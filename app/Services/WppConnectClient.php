<?php

namespace App\Services;

use App\Exceptions\WppConnectException;
use App\Models\WhatsAppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WppConnectClient
{
    private ?WhatsAppSetting $setting = null;

    public function setting(): WhatsAppSetting
    {
        return $this->setting ??= WhatsAppSetting::current();
    }

    public function connect(): array
    {
        $this->guardConfigured();
        $this->generateToken();

        $start = $this->request('post', $this->sessionPath('start-session'), [
            'webhook' => '',
            'waitQrCode' => true,
        ]);

        $connected = $this->detectConnected($start);
        $qrCode = $this->extractQrCode($start);

        if (! $connected) {
            try {
                $connection = $this->connectionResponse();
                $connected = $this->detectConnected($connection);
            } catch (WppConnectException) {
                // A sessão pode ainda estar preparando o primeiro QR Code.
            }
        }

        if (! $connected && ! $qrCode) {
            $qrCode = $this->safeQrCode();
        }

        $phone = $connected ? $this->safeConnectedPhone() : null;
        $this->storeConnectionState($connected, $phone, $connected ? 'connected' : 'awaiting_qr');

        return [
            'connected' => $connected,
            'status' => $connected ? 'connected' : 'awaiting_qr',
            'phone' => $phone,
            'qr_code' => $qrCode,
            'message' => $connected
                ? 'WhatsApp conectado com sucesso.'
                : 'Leia o QR Code com o WhatsApp para concluir a conexão.',
        ];
    }

    public function status(): array
    {
        $this->guardConfigured();

        if (blank($this->setting()->api_token)) {
            return [
                'connected' => false,
                'status' => 'not_started',
                'phone' => null,
                'qr_code' => null,
                'message' => 'A sessão ainda não foi iniciada.',
            ];
        }

        $response = $this->connectionResponse();
        $connected = $this->detectConnected($response);
        $phone = $connected ? $this->safeConnectedPhone() : null;
        $status = $connected
            ? 'connected'
            : ($this->setting()->connection_status === 'awaiting_qr' ? 'awaiting_qr' : 'disconnected');
        $qrCode = $status === 'awaiting_qr' ? $this->safeQrCode() : null;

        $this->storeConnectionState($connected, $phone, $status);

        return [
            'connected' => $connected,
            'status' => $status,
            'phone' => $phone ?: $this->setting()->connected_phone,
            'qr_code' => $qrCode,
            'message' => $connected
                ? 'WhatsApp conectado.'
                : ($status === 'awaiting_qr' ? 'Aguardando a leitura do QR Code.' : 'WhatsApp desconectado.'),
        ];
    }

    public function sendText(string $phone, string $message): array
    {
        $payload = $this->request('post', $this->sessionPath('send-message'), [
            'phone' => $this->normalizePhone($phone),
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'message' => $message,
        ]);

        $this->guardSuccessfulPayload($payload);

        return $payload;
    }

    public function sendImage(
        string $phone,
        string $contents,
        string $filename,
        string $caption = '',
    ): array {
        if ($contents === '') {
            throw new InvalidArgumentException('A imagem não pode estar vazia.');
        }

        $payload = $this->request('post', $this->sessionPath('send-image'), [
            'phone' => $this->normalizePhone($phone),
            'isGroup' => false,
            'isNewsletter' => false,
            'isLid' => false,
            'filename' => $filename,
            'caption' => $caption,
            'base64' => base64_encode($contents),
        ]);

        $this->guardSuccessfulPayload($payload);

        return $payload;
    }

    public function providerReference(array $payload): ?string
    {
        foreach (['_serialized', 'messageId', 'message_id', 'id'] as $key) {
            $value = $this->findByKey($payload, $key);
            if (is_scalar($value) && filled((string) $value)) {
                return Str::limit((string) $value, 255, '');
            }
        }

        return null;
    }

    public function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (in_array(strlen($normalized), [10, 11], true)) {
            $normalized = '55'.$normalized;
        }

        if (! str_starts_with($normalized, '55') || ! in_array(strlen($normalized), [12, 13], true)) {
            throw new InvalidArgumentException('Informe o telefone com DDI e DDD.');
        }

        return $normalized;
    }

    private function generateToken(): string
    {
        $setting = $this->setting();
        $path = '/api/'.rawurlencode($setting->session_name).'/'.rawurlencode($setting->secret_key).'/generate-token';
        $payload = $this->request('post', $path, [], false);
        $token = $this->findByKey($payload, 'token');

        if (! is_string($token) || blank($token)) {
            throw new WppConnectException('O WPPConnect não retornou o token da sessão.');
        }

        $setting->forceFill([
            'api_token' => $token,
            'last_error' => null,
        ])->save();

        return $token;
    }

    private function ensureToken(): void
    {
        $this->guardConfigured();

        if (blank($this->setting()->api_token)) {
            $this->generateToken();
        }
    }

    private function connectionResponse(): array
    {
        return $this->request('get', $this->sessionPath('check-connection-session'));
    }

    private function safeQrCode(): ?string
    {
        try {
            return $this->extractQrCode(
                $this->request('get', $this->sessionPath('qrcode-session')),
            );
        } catch (WppConnectException) {
            return null;
        }
    }

    private function safeConnectedPhone(): ?string
    {
        try {
            $payload = $this->request('get', $this->sessionPath('get-phone-number'));
            $candidate = $this->findByKey($payload, 'phone')
                ?? $this->findByKey($payload, 'number')
                ?? Arr::get($payload, 'response');

            if (! is_scalar($candidate)) {
                return null;
            }

            $phone = preg_replace('/\D+/', '', (string) $candidate) ?? '';

            return strlen($phone) >= 10 ? $phone : null;
        } catch (WppConnectException) {
            return null;
        }
    }

    private function request(string $method, string $path, array $body = [], bool $authenticated = true): array
    {
        if ($authenticated) {
            $this->ensureToken();
        }

        $request = $this->pendingRequest();
        if ($authenticated) {
            $request = $request->withToken($this->setting()->api_token);
        }

        try {
            $response = match ($method) {
                'get' => $request->get($this->url($path)),
                'post' => $request->post($this->url($path), $body),
                default => throw new InvalidArgumentException("Método HTTP {$method} não suportado."),
            };
        } catch (ConnectionException) {
            throw new WppConnectException('Não foi possível acessar o servidor WPPConnect.');
        }

        if ($response->failed()) {
            $message = $this->responseMessage($response);
            throw new WppConnectException(
                'O WPPConnect respondeu com HTTP '.$response->status().($message ? ": {$message}" : '.'),
            );
        }

        return $this->responsePayload($response);
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.wppconnect.connect_timeout', 5))
            ->timeout((int) config('services.wppconnect.timeout', 30));
    }

    private function responsePayload(Response $response): array
    {
        $contentType = strtolower($response->header('Content-Type', ''));
        if (str_starts_with($contentType, 'image/')) {
            return ['qrcode' => 'data:'.$contentType.';base64,'.base64_encode($response->body())];
        }

        $json = $response->json();
        if (is_array($json)) {
            return $json;
        }

        if (is_scalar($json) && filled((string) $json)) {
            return ['response' => $json];
        }

        return ['response' => $response->body()];
    }

    private function responseMessage(Response $response): ?string
    {
        $json = $response->json();
        if (is_array($json)) {
            foreach (['message', 'error', 'response'] as $key) {
                $value = $this->findByKey($json, $key);
                if (is_scalar($value) && filled((string) $value)) {
                    return Str::limit(strip_tags((string) $value), 250);
                }
            }
        }

        $body = trim(strip_tags($response->body()));

        return $body === '' ? null : Str::limit($body, 250);
    }

    private function extractQrCode(array $payload): ?string
    {
        foreach (['qrcode', 'qrCode', 'qr_code', 'qrcodeBase64', 'base64'] as $key) {
            $value = $this->findByKey($payload, $key);
            if (! is_string($value) || blank($value)) {
                continue;
            }

            if (str_starts_with($value, 'data:image/') || Str::startsWith($value, ['https://', 'http://'])) {
                return $value;
            }

            $compact = preg_replace('/\s+/', '', $value) ?? '';
            if (strlen($compact) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', $compact)) {
                return 'data:image/png;base64,'.$compact;
            }
        }

        return null;
    }

    private function detectConnected(array $payload): bool
    {
        foreach (['connected', 'isConnected', 'isLogged', 'logged'] as $key) {
            $value = $this->findByKey($payload, $key);
            if ($value === true || $value === 1 || $value === 'true') {
                return true;
            }
        }

        $status = $this->findByKey($payload, 'status');
        if ($status === true || $status === 1) {
            return true;
        }

        foreach ($this->scalarValues($payload) as $value) {
            $state = strtoupper(trim((string) $value));
            if (Str::contains($state, ['DISCONNECTED', 'NOT CONNECTED', 'UNPAIRED', 'CLOSED'])) {
                continue;
            }
            if (in_array($state, ['CONNECTED', 'ISLOGGED', 'INCHAT'], true)
                || Str::contains($state, ['SESSION CONNECTED', 'PHONE CONNECTED'])) {
                return true;
            }
        }

        return false;
    }

    private function guardSuccessfulPayload(array $payload): void
    {
        $status = $this->findByKey($payload, 'status');

        if ($status === false || $status === 0
            || (is_string($status) && in_array(strtolower($status), ['error', 'failed', 'fail'], true))) {
            $message = $this->findByKey($payload, 'message') ?? $this->findByKey($payload, 'error');
            throw new WppConnectException(
                is_scalar($message) ? Str::limit((string) $message, 250) : 'O WPPConnect recusou o envio.',
            );
        }
    }

    private function guardConfigured(): void
    {
        if (! $this->setting()->isConfigured()) {
            throw new WppConnectException('Configure o servidor WPPConnect antes de iniciar a conexão.');
        }
    }

    private function sessionPath(string $endpoint): string
    {
        return '/api/'.rawurlencode($this->setting()->session_name).'/'.$endpoint;
    }

    private function url(string $path): string
    {
        return rtrim($this->setting()->api_url, '/').'/'.ltrim($path, '/');
    }

    private function storeConnectionState(bool $connected, ?string $phone, string $status): void
    {
        $attributes = [
            'connection_status' => $status,
            'last_error' => null,
        ];

        if ($connected) {
            $attributes['connected_phone'] = $phone ?: $this->setting()->connected_phone;
            $attributes['last_connected_at'] = now();
        }

        $this->setting()->forceFill($attributes)->save();
    }

    private function findByKey(array $payload, string $wanted): mixed
    {
        foreach ($payload as $key => $value) {
            if (strcasecmp((string) $key, $wanted) === 0) {
                return $value;
            }
            if (is_array($value)) {
                $found = $this->findByKey($value, $wanted);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function scalarValues(array $payload): array
    {
        $values = [];
        array_walk_recursive($payload, function (mixed $value) use (&$values): void {
            if (is_scalar($value)) {
                $values[] = $value;
            }
        });

        return $values;
    }
}

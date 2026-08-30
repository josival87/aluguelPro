<?php

namespace App\Jobs;

use App\Models\MiaReceipt;
use App\Services\MiaReceiptClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendMiaReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public readonly int $miaReceiptId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(MiaReceiptClient $client): void
    {
        $receipt = MiaReceipt::find($this->miaReceiptId);
        if (! $receipt || $receipt->status !== MiaReceipt::STATUS_PENDING) {
            return;
        }

        $receipt->increment('attempts');
        $receipt->refresh();

        try {
            $response = $client->create($receipt);
        } catch (ConnectionException $exception) {
            if ($this->confirmAfterUncertainResponse($receipt, $client)) {
                return;
            }

            $this->markPendingError($receipt, null, 'Falha de conexão ao criar o recebimento na Mia.');

            throw new RuntimeException('Falha de conexão com a Mia; o envio será repetido.', previous: $exception);
        }

        if (in_array($response->status(), [200, 201], true)) {
            $this->markSent($receipt, $response);

            return;
        }

        if ($response->status() === 429) {
            $this->markPendingError($receipt, 429, $this->responseError($response));
            $this->release($this->retryAfter($response));

            return;
        }

        if ($response->serverError()) {
            if ($this->confirmAfterUncertainResponse($receipt, $client)) {
                return;
            }

            $this->markPendingError($receipt, $response->status(), $this->responseError($response));

            throw new RuntimeException('A Mia respondeu com erro de servidor; o envio será repetido.');
        }

        $this->markFailed($receipt, $response->status(), $this->responseError($response));
    }

    public function failed(?Throwable $exception): void
    {
        $receipt = MiaReceipt::find($this->miaReceiptId);
        if (! $receipt || $receipt->status !== MiaReceipt::STATUS_PENDING) {
            return;
        }

        $message = $exception
            ? 'Tentativas esgotadas: '.Str::limit($exception->getMessage(), 900, '')
            : 'As tentativas de envio para a Mia foram esgotadas.';

        $this->markFailed($receipt, $receipt->last_http_status, $message);
    }

    private function confirmAfterUncertainResponse(MiaReceipt $receipt, MiaReceiptClient $client): bool
    {
        try {
            $response = $client->find($receipt);
        } catch (ConnectionException) {
            return false;
        }

        if ($response->successful()) {
            $this->markSent($receipt, $response);

            return true;
        }

        if ($response->status() !== 404 && $response->clientError() && $response->status() !== 429) {
            $this->markFailed($receipt, $response->status(), $this->responseError($response));

            return true;
        }

        return false;
    }

    private function markSent(MiaReceipt $receipt, Response $response): void
    {
        $receipt->update([
            'status' => MiaReceipt::STATUS_SENT,
            'mia_receipt_id' => is_numeric($response->json('data.id')) ? (int) $response->json('data.id') : null,
            'last_http_status' => $response->status(),
            'last_error' => null,
            'sent_at' => now(),
        ]);
    }

    private function markPendingError(MiaReceipt $receipt, ?int $status, string $message): void
    {
        $receipt->update([
            'last_http_status' => $status,
            'last_error' => Str::limit($message, 1000, ''),
        ]);
    }

    private function markFailed(MiaReceipt $receipt, ?int $status, string $message): void
    {
        $receipt->update([
            'status' => MiaReceipt::STATUS_FAILED,
            'last_http_status' => $status,
            'last_error' => Str::limit($message, 1000, ''),
        ]);

        Log::error('O recebimento não pôde ser enviado para a Mia.', [
            'mia_receipt_id' => $receipt->getKey(),
            'charge_id' => $receipt->charge_id,
            'http_status' => $status,
        ]);
    }

    private function responseError(Response $response): string
    {
        $message = $response->json('message');
        $errorFields = array_keys((array) $response->json('errors', []));
        $details = is_string($message) && $message !== '' ? $message : 'Resposta HTTP '.$response->status().'.';

        if ($errorFields !== []) {
            $details .= ' Campos: '.implode(', ', $errorFields).'.';
        }

        return $details;
    }

    private function retryAfter(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        return is_numeric($retryAfter) ? max(1, min(3600, (int) $retryAfter)) : 60;
    }
}

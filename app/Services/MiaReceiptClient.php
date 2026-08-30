<?php

namespace App\Services;

use App\Models\MiaReceipt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MiaReceiptClient
{
    public function create(MiaReceipt $receipt): Response
    {
        return $this->request()->post(
            'api/v1/clientes/'.$receipt->mia_client_id.'/recebimentos',
            $receipt->payload,
        );
    }

    public function find(MiaReceipt $receipt): Response
    {
        return $this->request()->get(
            'api/v1/clientes/'.$receipt->mia_client_id.'/recebimentos/'.rawurlencode($receipt->external_id),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.mia.url'), '/').'/')
            ->withToken((string) config('services.mia.token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.mia.connect_timeout', 5))
            ->timeout((int) config('services.mia.timeout', 15));
    }
}

<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PixPayment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PixService
{
    public function __construct(private MoneyCalculator $calculator) {}

    public function createFor(Charge $charge): PixPayment
    {
        if ($charge->status !== 'open') {
            throw ValidationException::withMessages([
                'pix' => 'O Pix só pode ser gerado para uma cobrança em aberto.',
            ]);
        }

        $charge->loadMissing('lease.property.group');
        $values = $this->calculator->payable($charge);
        $txid = strtoupper(Str::random(25));
        $group = $charge->lease->property->group;
        $payload = $this->generateStaticPix(
            key: (string) $group?->pix_key,
            recipient: (string) $group?->responsible_name,
            city: (string) $charge->lease->property->city,
            amount: $values['total'],
            txid: $txid,
            description: 'AlugaPro '.$charge->type.' #'.$charge->id,
        );

        return $charge->pixPayments()->create([
            'txid' => $txid,
            'original_amount' => $values['original'],
            'fine_amount' => $values['fine'],
            'interest_amount' => $values['interest'],
            'total_amount' => $values['total'],
            'br_code' => $payload,
            'provider' => 'local_emv_static',
            'status' => 'active',
            'expires_at' => now()->addMinutes(config('business.pix_expiration_minutes')),
        ]);
    }

    public function generateStaticPix(
        string $key,
        float $amount,
        string $recipient,
        string $city,
        string $txid = '***',
        ?string $description = null,
    ): string
    {
        try {
            $key = $this->normalizeKey($key);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['pix' => $exception->getMessage()]);
        }

        $recipient = $this->ascii($recipient, 25);
        $city = $this->ascii($city, 15);
        $txid = $this->txid($txid);

        if ($recipient === '' || $city === '') {
            throw ValidationException::withMessages([
                'pix' => 'O nome do responsável e a cidade do imóvel são obrigatórios para gerar o Pix.',
            ]);
        }

        if (! is_finite($amount) || $amount <= 0) {
            throw ValidationException::withMessages([
                'pix' => 'O valor da cobrança deve ser maior que zero para gerar o Pix.',
            ]);
        }

        $merchant = $this->field('00', 'br.gov.bcb.pix').$this->field('01', $key);
        $description = $this->ascii((string) $description, 72);
        $descriptionLimit = 99 - strlen($merchant) - 4;
        if ($description !== '' && $descriptionLimit > 0) {
            $merchant .= $this->field('02', substr($description, 0, $descriptionLimit));
        }

        $payload = $this->field('00', '01').
            $this->field('26', $merchant).
            $this->field('52', '0000').
            $this->field('53', '986').
            $this->field('54', number_format($amount, 2, '.', '')).
            $this->field('58', 'BR').
            $this->field('59', $this->ascii($recipient, 25)).
            $this->field('60', $this->ascii($city, 15)).
            $this->field('62', $this->field('05', $txid)).'6304';

        return $payload.strtoupper(str_pad(dechex($this->crc16($payload)), 4, '0', STR_PAD_LEFT));
    }

    public function normalizeKey(string $key): string
    {
        $key = trim($key);
        $digits = preg_replace('/\D/', '', $key) ?? '';

        if (preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $key)) {
            return $digits;
        }

        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $key)) {
            return $digits;
        }

        if (filter_var($key, FILTER_VALIDATE_EMAIL)
            || preg_match('/^\+55\d{10,11}$/', $key)
            || preg_match('/^\d{11}$/', $key)
            || preg_match('/^\d{14}$/', $key)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return $key;
        }

        if (preg_match('/^[A-Z0-9]{12}\d{2}$/i', $key)) {
            return strtoupper($key);
        }

        throw new InvalidArgumentException(
            'A chave Pix do grupo é inválida. Use e-mail, CPF/CNPJ, telefone no formato +55DDDNUMERO ou chave aleatória UUID.'
        );
    }

    private function field(string $id, string $value): string
    {
        if (strlen($value) > 99) {
            throw ValidationException::withMessages([
                'pix' => 'Não foi possível montar o Pix porque um dos campos excede o limite do padrão EMVCo.',
            ]);
        }

        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function ascii(string $value, int $limit): string
    {
        return Str::of($value)->ascii()->upper()->replaceMatches('/[^A-Z0-9 .-]/', '')->limit($limit, '')->toString();
    }

    private function txid(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '***') {
            return $value;
        }

        $value = preg_replace('/[^A-Z0-9]/', '', Str::ascii($value)) ?? '';
        if ($value === '' || strlen($value) > 25) {
            throw ValidationException::withMessages([
                'pix' => 'O identificador da transação Pix deve ter de 1 a 25 letras ou números.',
            ]);
        }

        return $value;
    }

    private function crc16(string $payload): int
    {
        $crc = 0xFFFF;
        foreach (str_split($payload) as $character) {
            $crc ^= ord($character) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return $crc;
    }
}

<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PixPayment;
use Illuminate\Support\Str;

class PixService
{
    public function __construct(private MoneyCalculator $calculator) {}

    public function createFor(Charge $charge): PixPayment
    {
        $charge->loadMissing('lease.property.group');
        $values = $this->calculator->payable($charge);
        $txid = strtoupper(Str::random(25));
        $group = $charge->lease->property->group;
        $payload = $this->brCode(
            key: $group->pix_key,
            recipient: $group->responsible_name,
            city: $charge->lease->property->city,
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
            'provider' => 'local_emv',
            'status' => 'active',
            'expires_at' => now()->addMinutes(config('business.pix_expiration_minutes')),
        ]);
    }

    private function brCode(string $key, string $recipient, string $city, float $amount, string $txid, string $description): string
    {
        $merchant = $this->field('00', 'br.gov.bcb.pix').$this->field('01', $key).
            $this->field('02', $this->ascii($description, 72));

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

    private function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function ascii(string $value, int $limit): string
    {
        return Str::of($value)->ascii()->upper()->replaceMatches('/[^A-Z0-9 .-]/', '')->limit($limit, '')->toString();
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

<?php

namespace App\Services;

use App\Models\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargePaymentService
{
    /** @return array{charge: Charge, changed: bool} */
    public function settle(Charge|int $charge, string $paymentMethod = 'manual'): array
    {
        $chargeId = $charge instanceof Charge ? $charge->getKey() : $charge;

        return DB::transaction(function () use ($chargeId, $paymentMethod) {
            $locked = Charge::query()->lockForUpdate()->findOrFail($chargeId);
            if ($locked->status === 'paid') {
                return ['charge' => $locked, 'changed' => false];
            }
            $locked->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => $paymentMethod]);
            $this->cancelActivePix($locked);

            return ['charge' => $locked->fresh(), 'changed' => true];
        });
    }

    /** @return array{charge: Charge, changed: bool} */
    public function adjustAmount(Charge|int $charge, float $amount, ?int $userId = null): array
    {
        $chargeId = $charge instanceof Charge ? $charge->getKey() : $charge;
        $amount = round($amount, 2);
        if (! is_finite($amount) || $amount < 0.01 || $amount > 9999999999.99) {
            throw ValidationException::withMessages([
                'amount' => 'Informe um valor válido maior que zero.',
            ]);
        }

        return DB::transaction(function () use ($chargeId, $amount, $userId) {
            $locked = Charge::query()->lockForUpdate()->findOrFail($chargeId);
            $this->ensureOpen($locked);
            $previousAmount = round((float) $locked->amount, 2);

            if ($previousAmount === $amount) {
                return ['charge' => $locked, 'changed' => false];
            }

            $locked->update(['amount' => $amount]);
            $this->cancelActivePix($locked);
            $locked->adjustments()->create([
                'user_id' => $userId,
                'previous_amount' => $previousAmount,
                'new_amount' => $amount,
                'action' => 'amount_updated',
            ]);

            return ['charge' => $locked->fresh(), 'changed' => true];
        });
    }

    /** @return array{charge: Charge, changed: bool} */
    public function waive(Charge|int $charge, ?int $userId = null): array
    {
        $chargeId = $charge instanceof Charge ? $charge->getKey() : $charge;

        return DB::transaction(function () use ($chargeId, $userId) {
            $locked = Charge::query()->lockForUpdate()->findOrFail($chargeId);
            $this->ensureOpen($locked);
            $previousAmount = round((float) $locked->amount, 2);

            $locked->update([
                'amount' => 0,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'waiver',
            ]);
            $this->cancelActivePix($locked);
            $locked->adjustments()->create([
                'user_id' => $userId,
                'previous_amount' => $previousAmount,
                'new_amount' => 0,
                'action' => 'waived',
            ]);

            return ['charge' => $locked->fresh(), 'changed' => true];
        });
    }

    /** @return array{charge: Charge, changed: bool} */
    public function reopen(Charge|int $charge): array
    {
        $chargeId = $charge instanceof Charge ? $charge->getKey() : $charge;

        return DB::transaction(function () use ($chargeId) {
            $locked = Charge::query()->lockForUpdate()->findOrFail($chargeId);
            if ($locked->status === 'open') {
                return ['charge' => $locked, 'changed' => false];
            }
            $locked->update(['status' => 'open', 'paid_at' => null, 'payment_method' => null]);

            return ['charge' => $locked->fresh(), 'changed' => true];
        });
    }

    private function ensureOpen(Charge $charge): void
    {
        if ($charge->status !== 'open') {
            throw ValidationException::withMessages([
                'charge' => 'Reabra a cobrança antes de alterar ou zerar o valor.',
            ]);
        }
    }

    private function cancelActivePix(Charge $charge): void
    {
        $charge->pixPayments()->where('status', 'active')->update(['status' => 'cancelled']);
    }
}

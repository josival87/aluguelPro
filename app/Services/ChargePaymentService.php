<?php

namespace App\Services;

use App\Models\Charge;
use Illuminate\Support\Facades\DB;

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
}

<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Lease;
use Carbon\Carbon;

class BillingService
{
    public function generateMonth(Carbon $month): int
    {
        $created = 0;
        $referenceMonth = $month->copy()->startOfMonth();

        Lease::query()->whereIn('status', Lease::IN_FORCE_STATUSES)->chunkById(100, function ($leases) use ($referenceMonth, &$created) {
            foreach ($leases as $lease) {
                if ($lease->start_date?->copy()->startOfMonth()->gt($referenceMonth)) {
                    continue;
                }

                $dueDate = $referenceMonth->copy()->day(min($lease->due_day, $referenceMonth->daysInMonth));
                $charge = Charge::firstOrCreate([
                    'lease_id' => $lease->id,
                    'type' => 'rent',
                    'reference_month' => $referenceMonth->toDateTimeString(),
                ], [
                    'client_id' => $lease->client_id,
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $lease->rent_amount,
                    'status' => 'open',
                    'description' => 'Aluguel de '.$referenceMonth->translatedFormat('F/Y'),
                ]);
                if ($charge->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }
}

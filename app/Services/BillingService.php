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
        Lease::query()->where('status', 'active')->with('client')->chunkById(100, function ($leases) use ($month, &$created) {
            foreach ($leases as $lease) {
                if ($lease->start_date?->startOfMonth()->gt($month->copy()->startOfMonth())) continue;
                if ($lease->end_date?->endOfMonth()->lt($month->copy()->startOfMonth())) continue;

                $dueDate = $month->copy()->day(min($lease->due_day, $month->daysInMonth));
                $charge = Charge::firstOrCreate([
                    'lease_id' => $lease->id,
                    'type' => 'rent',
                    'reference_month' => $month->copy()->startOfMonth()->toDateString(),
                ], [
                    'client_id' => $lease->client_id,
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $lease->rent_amount,
                    'status' => 'open',
                    'description' => 'Aluguel de '.$month->translatedFormat('F/Y'),
                ]);
                if ($charge->wasRecentlyCreated) $created++;
            }
        });
        return $created;
    }
}

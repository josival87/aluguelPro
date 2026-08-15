<?php

namespace App\Services;

use App\Models\Charge;
use Carbon\CarbonInterface;

class MoneyCalculator
{
    /** @return array{original: float, fine: float, interest: float, total: float, days_late: int} */
    public function payable(Charge $charge, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $dueDate = (clone $charge->due_date)->startOfDay();
        $calculationDate = (clone $at)->startOfDay();
        $daysLate = $dueDate->diffInDays($calculationDate, false);
        $daysLate = max(0, (int) floor($daysLate));
        $original = round((float) $charge->amount, 2);
        $fine = $daysLate > 0 ? round($original * config('business.late_fee_percent') / 100, 2) : 0.0;
        $dailyRate = config('business.monthly_interest_percent') / 100 / 30;
        $interest = $daysLate > 0 ? round($original * $dailyRate * $daysLate, 2) : 0.0;

        return [
            'original' => $original,
            'fine' => $fine,
            'interest' => $interest,
            'total' => round($original + $fine + $interest, 2),
            'days_late' => $daysLate,
        ];
    }
}

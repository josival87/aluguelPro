<?php

namespace Tests\Unit;

use App\Models\Charge;
use App\Services\MoneyCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class MoneyCalculatorTest extends TestCase
{
    public function test_it_does_not_add_costs_before_the_due_date(): void
    {
        $charge = new Charge(['amount' => 1000, 'due_date' => '2026-08-10']);

        $result = app(MoneyCalculator::class)->payable($charge, Carbon::parse('2026-08-09'));

        $this->assertSame(0, $result['days_late']);
        $this->assertSame(0.0, $result['fine']);
        $this->assertSame(1000.0, $result['total']);
    }

    public function test_it_calculates_contractual_fine_and_daily_interest(): void
    {
        config(['business.late_fee_percent' => 2.0, 'business.monthly_interest_percent' => 1.0]);
        $charge = new Charge(['amount' => 1000, 'due_date' => '2026-08-10']);

        $result = app(MoneyCalculator::class)->payable($charge, Carbon::parse('2026-08-25'));

        $this->assertSame(15, $result['days_late']);
        $this->assertSame(20.0, $result['fine']);
        $this->assertSame(5.0, $result['interest']);
        $this->assertSame(1025.0, $result['total']);
    }
}

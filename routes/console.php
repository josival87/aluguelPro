<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$billingTimezone = config('business.billing_timezone', 'America/Sao_Paulo');

Schedule::command('billing:generate --next')
    ->dailyAt('23:55')
    ->timezone($billingTimezone)
    ->when(fn (): bool => now($billingTimezone)->isLastOfMonth())
    ->withoutOverlapping();
Schedule::command('billing:remind')
    ->dailyAt('09:00')
    ->timezone($billingTimezone)
    ->withoutOverlapping();
Schedule::command('mia:dispatch-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();

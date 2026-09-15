<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leases')
            ->where('status', 'active')
            ->whereDate('end_date', '<', now(config('business.billing_timezone', 'America/Sao_Paulo'))->toDateString())
            ->update(['status' => 'active_expired']);
    }

    public function down(): void
    {
        DB::table('leases')->where('status', 'active_expired')->update(['status' => 'active']);
    }
};

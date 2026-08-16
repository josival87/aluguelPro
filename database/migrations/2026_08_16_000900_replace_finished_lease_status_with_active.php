<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leases')->where('status', 'finished')->update(['status' => 'active']);
    }

    public function down(): void
    {
        // The former status did not distinguish active leases reliably.
    }
};

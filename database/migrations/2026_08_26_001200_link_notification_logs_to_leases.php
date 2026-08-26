<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignId('lease_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['lease_id', 'created_at']);
        });

        DB::table('notification_logs')
            ->whereNotNull('charge_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                $leaseIds = DB::table('charges')
                    ->whereIn('id', $logs->pluck('charge_id')->filter())
                    ->pluck('lease_id', 'id');

                foreach ($logs as $log) {
                    if ($leaseId = $leaseIds->get($log->charge_id)) {
                        DB::table('notification_logs')->where('id', $log->id)->update(['lease_id' => $leaseId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropIndex(['lease_id', 'created_at']);
            $table->dropConstrainedForeignId('lease_id');
        });
    }
};

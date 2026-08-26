<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('generation_key', 40)->nullable()->after('type');
        });

        DB::table('charges')
            ->select(['id', 'type', 'reference_month'])
            ->orderBy('id')
            ->chunkById(100, function ($charges) {
                foreach ($charges as $charge) {
                    DB::table('charges')->where('id', $charge->id)->update([
                        'generation_key' => $charge->type.':'.Carbon::parse($charge->reference_month)->format('Y-m'),
                    ]);
                }
            });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropUnique('charges_lease_id_type_reference_month_unique');
            $table->unique(['lease_id', 'generation_key']);
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropUnique(['lease_id', 'generation_key']);
            $table->dropColumn('generation_key');
            $table->unique(['lease_id', 'type', 'reference_month']);
        });
    }
};

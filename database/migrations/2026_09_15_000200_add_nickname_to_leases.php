<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->string('nickname', 100)->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->dropColumn('nickname');
        });
    }
};

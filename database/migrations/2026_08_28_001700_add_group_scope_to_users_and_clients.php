<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('role')
                ->constrained('groups')
                ->restrictOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('user_id')
                ->constrained('groups')
                ->restrictOnDelete();
        });

        $clientGroups = DB::table('leases')
            ->join('properties', 'properties.id', '=', 'leases.property_id')
            ->orderBy('leases.id')
            ->get(['leases.client_id', 'properties.group_id'])
            ->unique('client_id');

        foreach ($clientGroups as $clientGroup) {
            DB::table('clients')
                ->where('id', $clientGroup->client_id)
                ->whereNull('group_id')
                ->update(['group_id' => $clientGroup->group_id]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original table stored the generated document. Preserve it as the
        // lease-specific snapshot before introducing reusable base contracts.
        Schema::rename('contracts', 'lease_contracts');

        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->renameColumn('content', 'final_content');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->longText('content');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('lease_id')->constrained('contracts')->restrictOnDelete();
            $table->json('tenant_signature')->nullable()->after('signed_at');
            $table->json('landlord_signature')->nullable()->after('tenant_signature');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('group_id')->constrained('contracts')->restrictOnDelete();
        });

        DB::table('lease_contracts')->where('status', 'draft')->update(['status' => 'in_production']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });

        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['tenant_signature', 'landlord_signature']);
        });

        Schema::dropIfExists('contracts');

        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->renameColumn('final_content', 'content');
        });

        DB::table('lease_contracts')->where('status', 'in_production')->update(['status' => 'draft']);
        Schema::rename('lease_contracts', 'contracts');
    }
};

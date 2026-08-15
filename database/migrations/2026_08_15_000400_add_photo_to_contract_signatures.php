<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_signatures', function (Blueprint $table) {
            $table->longText('photo_base64')->nullable()->after('verification_channel');
            $table->string('photo_mime_type', 100)->nullable()->after('photo_base64');
            $table->string('photo_sha256', 64)->nullable()->after('photo_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('contract_signatures', function (Blueprint $table) {
            $table->dropColumn(['photo_base64', 'photo_mime_type', 'photo_sha256']);
        });
    }
};

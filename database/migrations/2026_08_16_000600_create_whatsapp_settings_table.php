<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('singleton')->default(true)->unique();
            $table->string('api_url', 500);
            $table->string('session_name', 100)->default('alugapro');
            $table->text('secret_key');
            $table->text('api_token')->nullable();
            $table->string('connected_phone', 20)->nullable();
            $table->string('connection_status', 30)->default('configured');
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};

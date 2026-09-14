<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->string('graph_api_version', 20)->default('v26.0');
            $table->string('phone_number_id', 30)->nullable();
            $table->string('business_account_id', 30)->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('webhook_verify_token')->nullable();
            $table->json('message_templates')->nullable();
            $table->timestamp('webhook_subscribed_at')->nullable();
        });

        DB::table('whatsapp_settings')->update([
            'connected_phone' => null,
            'connection_status' => 'configured',
            'last_error' => null,
            'last_connected_at' => null,
        ]);

        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropColumn(['api_url', 'session_name', 'secret_key', 'api_token']);
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropColumn(['delivered_at', 'read_at']);
        });

        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->string('api_url', 500)->nullable();
            $table->string('session_name', 100)->nullable();
            $table->text('secret_key')->nullable();
            $table->text('api_token')->nullable();
        });

        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'graph_api_version',
                'phone_number_id',
                'business_account_id',
                'access_token',
                'app_secret',
                'webhook_verify_token',
                'message_templates',
                'webhook_subscribed_at',
            ]);
        });
    }
};

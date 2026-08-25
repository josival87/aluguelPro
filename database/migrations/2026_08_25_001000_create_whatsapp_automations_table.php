<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_automations', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->text('message');
            $table->timestamps();
        });

        $now = now();
        DB::table('whatsapp_automations')->insert([
            [
                'key' => 'due_in_5_days',
                'message' => 'Olá, {{cliente}}! Sua cobrança de {{valor}} vence em {{vencimento}}. Imóvel: {{imovel}}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'due_today',
                'message' => 'Olá, {{cliente}}! Sua cobrança de {{valor}} vence hoje ({{vencimento}}). Imóvel: {{imovel}}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'responsible_due_today',
                'message' => 'Vencimento hoje: {{imovel}}, cliente {{cliente}}, valor {{valor}}. Grupo: {{grupo}}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_automations');
    }
};

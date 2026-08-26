<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('whatsapp_automations')->updateOrInsert(
            ['key' => 'overdue'],
            [
                'message' => 'Olá, {{cliente}}! Sua cobrança de {{descricao}}, vencida em {{vencimento}}, permanece em atraso. Dias de atraso: {{dias_atraso}}. Valor atualizado: {{valor_atualizado}}. Imóvel: {{imovel}}.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('whatsapp_automations')->where('key', 'overdue')->delete();
    }
};

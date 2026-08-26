<?php

use App\Support\Cpf;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeUniqueColumn('users', 'cpf');
        $this->normalizeUniqueColumn('clients', 'cpf');
        $this->normalizeColumn('contract_signatures', 'signer_document');
        $this->normalizeSignatureSnapshots();
    }

    public function down(): void
    {
        // A pontuação é somente apresentação e não deve voltar ao banco.
    }

    private function normalizeUniqueColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($column)
            ->select('id', $column)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => [
                'id' => $row->id,
                'cpf' => $this->validCpf($row->{$column}, "{$table}.{$column} #{$row->id}"),
            ]);

        $duplicates = $rows->groupBy('cpf')->filter(fn ($items) => $items->count() > 1);
        if ($duplicates->isNotEmpty()) {
            $ids = $duplicates->flatten(1)->pluck('id')->implode(', ');
            throw new RuntimeException("Há CPFs duplicados em {$table} após remover a máscara (IDs: {$ids}).");
        }

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row['id'])->update([$column => $row['cpf']]);
        }
    }

    private function normalizeColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (DB::table($table)->whereNotNull($column)->select('id', $column)->orderBy('id')->get() as $row) {
            DB::table($table)->where('id', $row->id)->update([
                $column => $this->validCpf($row->{$column}, "{$table}.{$column} #{$row->id}"),
            ]);
        }
    }

    private function normalizeSignatureSnapshots(): void
    {
        if (! Schema::hasTable('lease_contracts')) {
            return;
        }

        $columns = ['tenant_signature', 'landlord_signature'];
        foreach (DB::table('lease_contracts')->select('id', ...$columns)->orderBy('id')->get() as $row) {
            $updates = [];
            foreach ($columns as $column) {
                if ($row->{$column} === null) {
                    continue;
                }

                $signature = is_string($row->{$column})
                    ? json_decode($row->{$column}, true, flags: JSON_THROW_ON_ERROR)
                    : (array) $row->{$column};

                if (! empty($signature['document'])) {
                    $signature['document'] = $this->validCpf(
                        $signature['document'],
                        "lease_contracts.{$column} #{$row->id}",
                    );
                    $updates[$column] = json_encode($signature, JSON_THROW_ON_ERROR);
                }
            }

            if ($updates !== []) {
                DB::table('lease_contracts')->where('id', $row->id)->update($updates);
            }
        }
    }

    private function validCpf(mixed $value, string $location): string
    {
        $cpf = Cpf::digits($value);
        if ($cpf === null || strlen($cpf) !== 11) {
            throw new RuntimeException("CPF inválido em {$location}; informe exatamente 11 números.");
        }

        return $cpf;
    }
};

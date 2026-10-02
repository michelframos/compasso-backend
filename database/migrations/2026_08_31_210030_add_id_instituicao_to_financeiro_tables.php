<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'contas',
        'conta_pagamentos',
        'categorias_contas',
        'contratos',
        'alunos_contratos',
        'configuracoes_pix',
    ];

    public function up(): void
    {
        $defaultInstituicaoId = DB::table('instituicoes')->where('slug', 'default')->value('id') ?? 1;

        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'id_instituicao')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->unsignedBigInteger('id_instituicao')->nullable()->after('id');
                });
            }

            DB::table($tableName)->whereNull('id_instituicao')->update(['id_instituicao' => $defaultInstituicaoId]);

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `id_instituicao` BIGINT UNSIGNED NOT NULL DEFAULT 1");

            if (! $this->hasIndex($tableName, "{$tableName}_id_instituicao_id_index")) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->index(['id_instituicao', 'id'], "{$tableName}_id_instituicao_id_index");
                });
            }

            if (! $this->hasForeignKey($tableName, 'id_instituicao')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreign('id_instituicao')->references('id')->on('instituicoes');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasColumn($tableName, 'id_instituicao')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($this->hasForeignKey($tableName, 'id_instituicao')) {
                    $table->dropForeign(['id_instituicao']);
                }

                if ($this->hasIndex($tableName, "{$tableName}_id_instituicao_id_index")) {
                    $table->dropIndex("{$tableName}_id_instituicao_id_index");
                }

                $table->dropColumn('id_instituicao');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn (object $index): bool => $index->Key_name === $indexName);
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$database, $table, $column]
        );

        return $result !== null;
    }
};

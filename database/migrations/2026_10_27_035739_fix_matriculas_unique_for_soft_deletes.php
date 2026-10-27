<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table): void {
            if ($this->hasIndex('matriculas', 'matriculas_id_instituicao_aluno_turma_unique')) {
                $table->dropUnique('matriculas_id_instituicao_aluno_turma_unique');
            }
        });

        // Unique só para matrículas ativas (deleted_at IS NULL). Soft-deletes não bloqueiam rematrícula.
        DB::statement("
            ALTER TABLE matriculas
            ADD COLUMN aluno_turma_unica VARCHAR(64)
                GENERATED ALWAYS AS (
                    IF(
                        deleted_at IS NULL AND id_turma IS NOT NULL,
                        CONCAT(id_instituicao, '-', id_aluno, '-', id_turma),
                        NULL
                    )
                ) STORED,
            ADD UNIQUE KEY matriculas_aluno_turma_ativa_unique (aluno_turma_unica)
        ");
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table): void {
            if ($this->hasIndex('matriculas', 'matriculas_aluno_turma_ativa_unique')) {
                $table->dropUnique('matriculas_aluno_turma_ativa_unique');
            }
            if (Schema::hasColumn('matriculas', 'aluno_turma_unica')) {
                $table->dropColumn('aluno_turma_unica');
            }
        });

        Schema::table('matriculas', function (Blueprint $table): void {
            if (! $this->hasIndex('matriculas', 'matriculas_id_instituicao_aluno_turma_unique')) {
                $table->unique(
                    ['id_instituicao', 'id_aluno', 'id_turma'],
                    'matriculas_id_instituicao_aluno_turma_unique'
                );
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_matricula')->nullable()->after('id_professor');
            $table->foreign('id_matricula')->references('id')->on('matriculas')->nullOnDelete();
        });

        // Popula id_matricula nas contas existentes que possuem id_aluno
        // Busca a matrícula mais recente ativa (ou qualquer matrícula) para cada aluno
        DB::statement("
            UPDATE contas c
            INNER JOIN (
                SELECT id_aluno, MAX(id) as id_matricula
                FROM matriculas
                WHERE deleted_at IS NULL
                GROUP BY id_aluno
            ) m ON c.id_aluno = m.id_aluno
            SET c.id_matricula = m.id_matricula
            WHERE c.id_aluno IS NOT NULL
              AND c.id_matricula IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->dropForeign(['id_matricula']);
            $table->dropColumn('id_matricula');
        });
    }
};

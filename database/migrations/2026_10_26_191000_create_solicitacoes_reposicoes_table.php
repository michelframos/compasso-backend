<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_reposicoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_aluno')->constrained('alunos');
            $table->foreignId('id_aula_turma_perdida')->constrained('aulas_turmas');
            $table->foreignId('id_aula_turma_destino')->nullable()->constrained('aulas_turmas');
            $table->text('motivo');
            $table->string('status', 20)->default('pendente');
            $table->foreignId('id_usuario_decisor')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_aluno', 'id_aula_turma_perdida', 'status'], 'reposicoes_aluno_aula_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_reposicoes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observacoes_alunos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_aluno')->constrained('alunos');
            $table->foreignId('id_professor')->constrained('professores');
            $table->foreignId('id_turma')->nullable()->constrained('turmas')->nullOnDelete();
            $table->enum('tipo', ['comportamento', 'evolucao', 'alerta', 'geral'])->default('geral');
            $table->text('texto');
            $table->boolean('visivel_responsavel')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'id_aluno', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observacoes_alunos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes_alunos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_aluno')->constrained('alunos');
            $table->foreignId('id_turma')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('id_professor')->constrained('professores');
            $table->date('data');
            $table->enum('tipo', ['pratica', 'teorica', 'apresentacao', 'participacao', 'outra'])->default('pratica');
            $table->decimal('nota', 4, 2)->nullable();
            $table->enum('conceito', ['excelente', 'bom', 'regular', 'insuficiente'])->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'id_aluno', 'data']);
            $table->index(['id_instituicao', 'id_turma', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes_alunos');
    }
};

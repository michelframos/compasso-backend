<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_aulas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_aula_turma')->nullable()->constrained('aulas_turmas')->nullOnDelete();
            $table->foreignId('id_professor')->constrained('professores');
            $table->string('tipo', 20);
            $table->text('motivo')->nullable();

            $table->date('data_sugerida')->nullable();
            $table->time('hora_inicio_sugerida')->nullable();
            $table->time('hora_termino_sugerida')->nullable();
            $table->foreignId('id_professor_substituto')->nullable()->constrained('professores')->nullOnDelete();

            $table->foreignId('id_turma')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('id_curso')->nullable()->constrained('cursos')->nullOnDelete();
            $table->foreignId('id_aluno_especifico')->nullable()->constrained('alunos')->nullOnDelete();
            $table->string('tipo_aula', 20)->nullable();

            $table->string('destino_cobranca', 20)->nullable();
            $table->string('status', 20)->default('pendente');
            $table->foreignId('id_usuario_decisor')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->text('motivo_decisao')->nullable();
            $table->foreignId('id_aula_gerada')->nullable()->constrained('aulas_turmas')->nullOnDelete();
            $table->boolean('aplicada_automaticamente')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'status'], 'solicitacoes_aulas_inst_status_index');
            $table->index(['id_aula_turma', 'status'], 'solicitacoes_aulas_aula_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_aulas');
    }
};

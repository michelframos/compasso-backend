<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_turmas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_turma')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('id_professor')->nullable()->constrained('professores')->nullOnDelete();
            $table->foreignId('id_usuario_autor')->constrained('usuarios');
            $table->foreignId('id_aula_turma')->nullable()->constrained('aulas_turmas')->nullOnDelete();
            $table->string('origem', 20)->default('manual');
            $table->string('titulo', 120);
            $table->text('mensagem');
            $table->string('publico', 20);
            $table->json('canais');
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_usuario_autor', 'origem', 'created_at'], 'avisos_turmas_autor_origem_index');
        });

        Schema::create('avisos_turmas_destinatarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_aviso_turma')->constrained('avisos_turmas')->cascadeOnDelete();
            $table->foreignId('id_aluno')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('tipo', 20);
            $table->string('nome');
            $table->string('canal', 20);
            $table->string('destino')->nullable();
            $table->string('status', 20)->default('pendente');
            $table->string('erro')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_aviso_turma', 'status'], 'avisos_turmas_dest_aviso_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_turmas_destinatarios');
        Schema::dropIfExists('avisos_turmas');
    }
};

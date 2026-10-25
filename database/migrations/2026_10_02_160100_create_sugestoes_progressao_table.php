<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugestoes_progressao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_matricula')->constrained('matriculas');
            $table->foreignId('id_nivel_atual')->nullable()->constrained('niveis')->nullOnDelete();
            $table->foreignId('id_nivel_sugerido')->constrained('niveis');
            $table->foreignId('id_professor')->constrained('professores');
            $table->enum('status', ['pendente', 'aprovada', 'rejeitada'])->default('pendente');
            $table->text('justificativa');
            $table->foreignId('id_turma_destino')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('id_usuario_decisor')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->text('motivo_decisao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'status', 'created_at']);
            $table->index(['id_instituicao', 'id_matricula', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestoes_progressao');
    }
};

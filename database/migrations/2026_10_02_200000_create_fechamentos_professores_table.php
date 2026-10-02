<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fechamentos_professores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_professor')->constrained('professores');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->decimal('salario_fixo', 10, 2)->default(0);
            $table->decimal('total_horas', 8, 2)->default(0);
            $table->decimal('valor_hora_aula', 10, 2)->default(0);
            $table->decimal('valor_comissao', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->json('detalhes');
            $table->foreignId('id_conta')->nullable()->constrained('contas')->nullOnDelete();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('fechado_em');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'ano', 'mes'], 'fechamentos_prof_competencia_index');
            $table->index(['id_instituicao', 'id_professor', 'ano', 'mes'], 'fechamentos_prof_professor_competencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_professores');
    }
};

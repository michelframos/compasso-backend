<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidades_professores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_professor')->constrained('professores')->cascadeOnDelete();
            $table->string('dia_semana', 10);
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'id_professor', 'dia_semana'], 'disponibilidades_prof_dia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades_professores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('aulas_presencas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_aula_turma');
            $table->unsignedBigInteger('id_aluno');
            $table->enum('status', ['presente','ausente','justificado'])->default('presente');

            $table->foreign('id_aula_turma')->references('id')->on('aulas_turmas');
            $table->foreign('id_aluno')->references('id')->on('alunos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aulas_presencas');
    }
};

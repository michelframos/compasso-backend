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
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_aluno');
            $table->unsignedBigInteger('id_turma');
            $table->date('data');
            $table->enum('status', ['ativa','pausada','cancelada','completada','transferida'])->default('ativa');
            $table->text('observacoes')->nullable();

            $table->foreign('id_aluno')->references('id')->on('alunos');
            $table->foreign('id_turma')->references('id')->on('turmas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};

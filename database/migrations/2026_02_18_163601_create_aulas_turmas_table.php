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
        Schema::create('aulas_turmas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_turma');
            $table->unsignedBigInteger('id_professor');
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->enum('status', ['agendada','concluida','cancelada'])->nullable()->default(null);
            $table->text('conteudo_dado')->nullable();

            $table->foreign('id_turma')->references('id')->on('turmas');
            $table->foreign('id_professor')->references('id')->on('professores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aulas_turmas');
    }
};

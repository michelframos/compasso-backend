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
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_curso');
            $table->unsignedBigInteger('id_nivel');
            $table->unsignedBigInteger('id_professor');
            $table->integer('maximo_alunos')->default(15);
            $table->text('descricao')->nullable();
            $table->text('observacoes')->nullable();

            $table->foreign('id_curso')->references('id')->on('cursos');
            $table->foreign('id_nivel')->references('id')->on('niveis');
            $table->foreign('id_professor')->references('id')->on('professores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turmas');
    }
};

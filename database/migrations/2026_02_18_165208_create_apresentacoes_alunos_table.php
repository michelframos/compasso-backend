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
        Schema::create('apresentacoes_alunos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_apresentacao');
            $table->unsignedBigInteger('id_aluno');
            $table->string('tamanho_figurino', 10)->nullable();
            $table->boolean('pago_figurino')->default(false);
            $table->boolean('presenca_ensaio_geral')->default(false);
            $table->timestamps();

            $table->foreign('id_apresentacao')->references('id')->on('apresentacoes')->onDelete('cascade');
            $table->foreign('id_aluno')->references('id')->on('alunos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apresentacoes_alunos');
    }
};

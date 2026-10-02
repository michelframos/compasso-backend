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
        Schema::create('apresentacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_espetaculo');
            $table->unsignedBigInteger('id_turma')->nullable();
            $table->string('titulo_musica')->nullable();
            $table->integer('ordem_entrada')->nullable();
            $table->time('duracao_estimada')->nullable();
            $table->timestamps();

            $table->foreign('id_espetaculo')->references('id')->on('espetaculos')->onDelete('cascade');
            $table->foreign('id_turma')->references('id')->on('turmas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apresentacoes');
    }
};

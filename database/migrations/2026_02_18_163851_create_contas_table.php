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
        Schema::create('contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_categoria');
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->enum('status', ['pendente','pago','vencido','cancelado'])->default('pendente');
            $table->text('observacoes')->nullable();

            $table->unsignedBigInteger('id_aluno')->nullable();
            $table->unsignedBigInteger('id_professor')->nullable();
            $table->enum('tipo', ['receita', 'despesa']);

            $table->foreign('id_categoria')->references('id')->on('categorias_contas');
            $table->foreign('id_aluno')->references('id')->on('alunos');
            $table->foreign('id_professor')->references('id')->on('professores');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};

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
        Schema::create('instrumento_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_instrumento')->constrained('instrumentos')->onDelete('cascade');
            $table->foreignId('id_aluno')->nullable()->constrained('alunos')->onDelete('set null');
            $table->enum('acao', ['emprestimo', 'devolucao', 'manutencao']);
            $table->dateTime('data');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrumento_historicos');
    }
};

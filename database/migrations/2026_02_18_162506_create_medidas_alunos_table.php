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
        Schema::create('medidas_alunos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_aluno');
            $table->decimal('medida_torax', 5, 2)->nullable();
            $table->decimal('medida_cintura', 5, 2)->nullable();
            $table->decimal('medida_quadril', 5, 2)->nullable();
            $table->decimal('medida_altura', 5, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->useCurrent();

            $table->foreign('id_aluno')->references('id')->on('alunos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medidas_alunos');
    }
};

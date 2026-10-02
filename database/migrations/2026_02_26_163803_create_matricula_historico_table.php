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
        Schema::create('matricula_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_matricula')->constrained('matriculas')->onDelete('cascade');
            $table->foreignId('id_turma_origem')->nullable()->constrained('turmas')->onDelete('set null');
            $table->foreignId('id_turma_destino')->constrained('turmas')->onDelete('cascade');
            $table->dateTime('data_transferencia');
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matricula_historico');
    }
};

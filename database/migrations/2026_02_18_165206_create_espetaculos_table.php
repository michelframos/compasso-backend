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
        Schema::create('espetaculos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->date('data_evento');
            $table->string('local')->nullable();
            $table->enum('status', ['planejamento', 'ensaios', 'concluido', 'cancelado'])->default('planejamento');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espetaculos');
    }
};

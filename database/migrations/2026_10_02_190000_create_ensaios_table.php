<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ensaios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_apresentacao')->constrained('apresentacoes')->cascadeOnDelete();
            $table->foreignId('id_professor')->nullable()->constrained('professores')->nullOnDelete();
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->string('local')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index(['id_instituicao', 'data']);
            $table->index(['id_instituicao', 'id_apresentacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ensaios');
    }
};

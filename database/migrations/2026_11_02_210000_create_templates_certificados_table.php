<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates_certificados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->string('nome');
            $table->longText('conteudo');
            $table->string('fundo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates_certificados');
    }
};

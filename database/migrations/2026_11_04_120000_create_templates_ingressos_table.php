<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates_ingressos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->decimal('largura', 10, 2);
            $table->decimal('altura', 10, 2);
            $table->string('unidade', 2)->default('cm'); // px|cm
            $table->longText('conteudo')->nullable();
            $table->string('fundo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates_ingressos');
    }
};

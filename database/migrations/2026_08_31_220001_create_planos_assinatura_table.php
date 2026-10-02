<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_assinatura', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco_mensal', 10, 2)->default(0);
            $table->unsignedInteger('limite_alunos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ativo', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos_assinatura');
    }
};

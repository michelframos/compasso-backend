<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depoimentos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('cargo');
            $table->string('escola');
            $table->text('conteudo');
            $table->string('avatar_url')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('aprovado')->default(false);
            $table->timestamp('aprovado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aprovado', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depoimentos');
    }
};

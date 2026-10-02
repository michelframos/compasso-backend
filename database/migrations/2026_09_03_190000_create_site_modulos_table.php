<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_modulos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->text('descricao');
            $table->json('recursos')->nullable();
            $table->string('icone', 64)->default('LayoutGrid');
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
        Schema::dropIfExists('site_modulos');
    }
};

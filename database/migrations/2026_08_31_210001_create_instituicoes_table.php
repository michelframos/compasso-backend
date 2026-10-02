<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituicoes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome_fantasia');
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->string('rua')->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('bairro')->nullable();
            $table->string('complemento')->nullable();
            $table->string('cep', 10)->nullable();
            $table->unsignedBigInteger('id_estado')->nullable();
            $table->unsignedBigInteger('id_cidade')->nullable();
            $table->string('status', 1)->default('a');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_estado')->references('id')->on('estados');
            $table->foreign('id_cidade')->references('id')->on('cidades');
            $table->index(['status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituicoes');
    }
};

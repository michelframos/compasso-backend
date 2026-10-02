<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_pix', function (Blueprint $table) {
            $table->id();
            $table->string('chave_pix', 140);
            $table->enum('tipo_chave', ['cpf', 'cnpj', 'email', 'telefone', 'evp']);
            $table->string('nome_beneficiario', 25);
            $table->string('cidade', 15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_pix');
    }
};

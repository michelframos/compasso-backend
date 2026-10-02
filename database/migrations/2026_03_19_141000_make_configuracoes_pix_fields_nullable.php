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
        Schema::table('configuracoes_pix', function (Blueprint $table) {
            $table->string('chave_pix', 140)->nullable()->change();
            $table->enum('tipo_chave', ['cpf', 'cnpj', 'email', 'telefone', 'evp'])->nullable()->change();
            $table->string('nome_beneficiario', 25)->nullable()->change();
            $table->string('cidade', 15)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes_pix', function (Blueprint $table) {
            $table->string('chave_pix', 140)->nullable(false)->change();
            $table->enum('tipo_chave', ['cpf', 'cnpj', 'email', 'telefone', 'evp'])->nullable(false)->change();
            $table->string('nome_beneficiario', 25)->nullable(false)->change();
            $table->string('cidade', 15)->nullable(false)->change();
        });
    }
};

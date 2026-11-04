<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingressos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes')->cascadeOnDelete();
            $table->foreignId('id_espetaculo')->constrained('espetaculos')->cascadeOnDelete();
            $table->foreignId('id_template_ingresso')->nullable()->constrained('templates_ingressos')->nullOnDelete();
            $table->string('numero', 40);
            $table->string('codigo_qr', 64);
            $table->decimal('valor', 10, 2)->default(0);
            $table->string('status', 20)->default('disponivel'); // disponivel|vendido|utilizado
            $table->boolean('cortesia')->default(false);
            $table->string('status_pagamento', 30)->nullable(); // aguardando_pagamento|pago|cancelado
            $table->foreignId('id_conta')->nullable()->constrained('contas')->nullOnDelete();
            $table->foreignId('id_cupom')->nullable()->constrained('cupons_ingresso')->nullOnDelete();
            $table->string('comprador_nome')->nullable();
            $table->string('comprador_documento', 30)->nullable();
            $table->timestamp('utilizado_em')->nullable();
            $table->longText('conteudo_gerado')->nullable();
            $table->string('fundo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_instituicao', 'numero']);
            $table->unique(['codigo_qr']);
            $table->index(['id_espetaculo', 'status']);
            $table->index(['id_conta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingressos');
    }
};

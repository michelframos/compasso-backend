<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->enum('modulo', ['agendamentos', 'contas_a_receber']);
            $table->enum('tipo', ['vencimento', 'atraso'])->nullable();
            $table->boolean('ativo')->default(false);
            $table->integer('dias_antecedencia')->default(3);
            $table->integer('intervalo_repeticao')->default(1);
            $table->integer('max_repeticoes')->nullable();
            $table->text('template_mensagem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_notificacoes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_assinatura', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_plano_assinatura')->constrained('planos_assinatura');
            $table->foreignId('id_usuario')->constrained('usuarios');
            $table->string('status', 20)->default('pendente');
            $table->text('observacao')->nullable();
            $table->foreignId('id_aprovado_por')->nullable()->constrained('usuarios');
            $table->timestamp('aprovado_em')->nullable();
            $table->timestamps();

            $table->index(['id_instituicao', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_assinatura');
    }
};

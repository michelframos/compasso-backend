<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes_disparadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuracao_notificacao_id')->constrained('configuracoes_notificacoes')->onDelete('cascade');
            $table->morphs('referencia');
            $table->string('numero_whatsapp');
            $table->enum('status', ['pendente', 'enviado', 'erro'])->default('pendente');
            $table->integer('tentativas')->default(0);
            $table->timestamp('disparado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes_disparadas');
    }
};

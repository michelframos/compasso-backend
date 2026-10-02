<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_impersonation_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_super_admin');
            $table->unsignedBigInteger('id_usuario_alvo');
            $table->unsignedBigInteger('id_instituicao');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('iniciado_em');
            $table->timestamp('encerrado_em')->nullable();
            $table->timestamps();

            $table->foreign('id_super_admin')->references('id')->on('usuarios');
            $table->foreign('id_usuario_alvo')->references('id')->on('usuarios');
            $table->foreign('id_instituicao')->references('id')->on('instituicoes');
            $table->foreign('token_id')->references('id')->on('personal_access_tokens')->nullOnDelete();
            $table->index(['id_instituicao', 'iniciado_em']);
            $table->index(['id_super_admin', 'encerrado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_impersonation_logs');
    }
};

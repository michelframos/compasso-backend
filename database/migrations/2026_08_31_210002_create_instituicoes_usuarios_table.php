<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituicoes_usuarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_instituicao');
            $table->unsignedBigInteger('id_usuario');
            $table->enum('role', ['admin', 'professor', 'aluno', 'responsavel', 'secretaria']);
            $table->string('status', 1)->default('a');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_instituicao', 'id_usuario']);
            $table->foreign('id_instituicao')->references('id')->on('instituicoes');
            $table->foreign('id_usuario')->references('id')->on('usuarios');
            $table->index(['id_instituicao', 'status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituicoes_usuarios');
    }
};

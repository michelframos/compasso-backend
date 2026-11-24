<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons_ingresso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes')->cascadeOnDelete();
            $table->foreignId('id_espetaculo')->nullable()->constrained('espetaculos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('tipo', 20); // percentual|valor_fixo
            $table->decimal('valor', 10, 2);
            $table->boolean('ativo')->default(true);
            $table->date('valido_de')->nullable();
            $table->date('valido_ate')->nullable();
            $table->unsignedInteger('uso_maximo')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->timestamps();

            $table->unique(['id_instituicao', 'codigo']);
            $table->index(['id_instituicao', 'id_espetaculo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons_ingresso');
    }
};

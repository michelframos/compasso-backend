<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('limite_reposicoes_periodo')->nullable()->after('permitir_aluno_editar_medidas');
            $table->unsignedSmallInteger('prazo_reposicao_dias')->nullable()->after('limite_reposicoes_periodo');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn(['limite_reposicoes_periodo', 'prazo_reposicao_dias']);
        });
    }
};

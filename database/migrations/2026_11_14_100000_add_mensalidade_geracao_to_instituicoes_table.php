<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->string('modo_geracao_mensalidades', 20)->default('formulario')->after('frequencia_minima');
            $table->unsignedTinyInteger('dia_vencimento_mensalidade')->default(10)->after('modo_geracao_mensalidades');
            $table->unsignedTinyInteger('quantidade_parcelas_mensalidade')->default(12)->after('dia_vencimento_mensalidade');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn([
                'modo_geracao_mensalidades',
                'dia_vencimento_mensalidade',
                'quantidade_parcelas_mensalidade',
            ]);
        });
    }
};

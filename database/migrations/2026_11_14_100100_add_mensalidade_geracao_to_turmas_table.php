<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->string('modo_geracao_mensalidades', 20)->nullable()->after('valor_mensalidade');
            $table->unsignedTinyInteger('dia_vencimento_mensalidade')->nullable()->after('modo_geracao_mensalidades');
            $table->unsignedTinyInteger('quantidade_parcelas_mensalidade')->nullable()->after('dia_vencimento_mensalidade');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->dropColumn([
                'modo_geracao_mensalidades',
                'dia_vencimento_mensalidade',
                'quantidade_parcelas_mensalidade',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->decimal('valor_hora_aula_aplicado', 10, 2)->nullable()->after('conteudo_dado');
            $table->decimal('percentual_comissao_aplicado', 5, 2)->nullable()->after('valor_hora_aula_aplicado');
            $table->decimal('valor_mensalidade_aplicado', 10, 2)->nullable()->after('percentual_comissao_aplicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->dropColumn(['valor_hora_aula_aplicado', 'percentual_comissao_aplicado', 'valor_mensalidade_aplicado']);
        });
    }
};

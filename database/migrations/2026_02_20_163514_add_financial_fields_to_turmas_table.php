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
        Schema::table('turmas', function (Blueprint $table) {
            $table->decimal('valor_mensalidade', 10, 2)->nullable()->default(0)->after('status');
            $table->decimal('percentual_comissao_especifico', 5, 2)->nullable()->after('valor_mensalidade');
            $table->decimal('valor_hora_aula_especifico', 10, 2)->nullable()->after('percentual_comissao_especifico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropColumn(['valor_mensalidade', 'percentual_comissao_especifico', 'valor_hora_aula_especifico']);
        });
    }
};

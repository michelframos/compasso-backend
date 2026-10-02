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
        Schema::table('contas', function (Blueprint $table) {
            $table->integer('numero_parcela')->nullable()->after('id_professor');
            $table->integer('quantidade_parcelas')->nullable()->after('numero_parcela');
            $table->integer('mes_referencia')->nullable()->after('quantidade_parcelas');
            $table->integer('ano_referencia')->nullable()->after('mes_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->dropColumn(['numero_parcela', 'quantidade_parcelas', 'mes_referencia', 'ano_referencia']);
        });
    }
};

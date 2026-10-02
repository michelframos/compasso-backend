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
        Schema::table('apresentacoes_alunos', function (Blueprint $table) {
            $table->boolean('presenca_espetaculo')->default(false)->after('presenca_ensaio_geral');
            $table->boolean('recebeu_figurino')->default(false)->after('presenca_espetaculo');
            $table->decimal('valor_figurino', 10, 2)->nullable()->after('pago_figurino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apresentacoes_alunos', function (Blueprint $table) {
            $table->dropColumn(['presenca_espetaculo', 'recebeu_figurino', 'valor_figurino']);
        });
    }
};

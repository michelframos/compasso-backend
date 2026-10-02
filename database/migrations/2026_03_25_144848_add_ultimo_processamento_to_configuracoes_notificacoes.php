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
        Schema::table('configuracoes_notificacoes', function (Blueprint $table) {
            $table->date('ultimo_processamento')->nullable()->after('horario_envio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes_notificacoes', function (Blueprint $table) {
            $table->dropColumn('ultimo_processamento');
        });
    }
};

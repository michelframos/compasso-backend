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
            $table->time('horario_envio')->default('08:00')->after('template_mensagem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes_notificacoes', function (Blueprint $table) {
            $table->dropColumn('horario_envio');
        });
    }
};

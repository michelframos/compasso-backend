<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Disparos deixam de ser só de WhatsApp de contas: avisos de turma também usam e-mail e não têm configuração automática. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes_disparadas', function (Blueprint $table): void {
            $table->unsignedBigInteger('configuracao_notificacao_id')->nullable()->change();
            $table->string('numero_whatsapp')->nullable()->change();
            $table->string('canal', 20)->default('whatsapp')->after('referencia_id');
            $table->string('email')->nullable()->after('numero_whatsapp');
            $table->string('erro')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('notificacoes_disparadas', function (Blueprint $table): void {
            $table->dropColumn(['canal', 'email', 'erro']);
        });
    }
};

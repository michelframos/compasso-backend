<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->string('codigo_ativacao')->nullable()->after('assinatura_status');
            $table->timestamp('codigo_ativacao_expira_em')->nullable()->after('codigo_ativacao');
            $table->timestamp('ativada_em')->nullable()->after('codigo_ativacao_expira_em');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn(['codigo_ativacao', 'codigo_ativacao_expira_em', 'ativada_em']);
        });
    }
};

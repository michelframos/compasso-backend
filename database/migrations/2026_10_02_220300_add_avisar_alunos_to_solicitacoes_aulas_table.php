<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes_aulas', function (Blueprint $table): void {
            $table->boolean('avisar_alunos')->default(true)->after('destino_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_aulas', function (Blueprint $table): void {
            $table->dropColumn('avisar_alunos');
        });
    }
};

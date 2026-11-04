<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos_contratos', function (Blueprint $table) {
            $table->timestamp('aceito_em')->nullable()->after('contrato_gerado');
            $table->foreignId('aceito_por_usuario_id')->nullable()->after('aceito_em')->constrained('usuarios')->nullOnDelete();
            $table->string('ip_aceite', 45)->nullable()->after('aceito_por_usuario_id');
            $table->text('user_agent_aceite')->nullable()->after('ip_aceite');
            $table->string('hash_conteudo', 64)->nullable()->after('user_agent_aceite');
        });
    }

    public function down(): void
    {
        Schema::table('alunos_contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aceito_por_usuario_id');
            $table->dropColumn(['aceito_em', 'ip_aceite', 'user_agent_aceite', 'hash_conteudo']);
        });
    }
};

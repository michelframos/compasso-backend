<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('espetaculos', function (Blueprint $table) {
            $table->time('hora_evento')->nullable()->after('data_evento');
            $table->dateTime('validade_ingresso_inicio')->nullable()->after('observacoes');
            $table->dateTime('validade_ingresso_fim')->nullable()->after('validade_ingresso_inicio');
            $table->foreignId('id_template_ingresso')->nullable()->after('validade_ingresso_fim')
                ->constrained('templates_ingressos')->nullOnDelete();
            $table->decimal('valor_ingresso_padrao', 10, 2)->nullable()->after('id_template_ingresso');
        });
    }

    public function down(): void
    {
        Schema::table('espetaculos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_template_ingresso');
            $table->dropColumn([
                'hora_evento',
                'validade_ingresso_inicio',
                'validade_ingresso_fim',
                'valor_ingresso_padrao',
            ]);
        });
    }
};

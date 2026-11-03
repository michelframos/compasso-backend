<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table): void {
            $table->foreignId('id_template_certificado')
                ->nullable()
                ->after('contrato_id')
                ->constrained('templates_certificados')
                ->nullOnDelete();
        });

        Schema::table('certificados', function (Blueprint $table): void {
            $table->longText('conteudo_gerado')->nullable()->after('carga_horaria');
            $table->string('fundo_path')->nullable()->after('conteudo_gerado');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_template_certificado');
        });

        Schema::table('certificados', function (Blueprint $table): void {
            $table->dropColumn(['conteudo_gerado', 'fundo_path']);
        });
    }
};

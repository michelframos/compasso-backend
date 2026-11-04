<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table) {
            $table->boolean('permitir_aluno_editar_medidas')->default(false)->after('limite_avisos_professor_dia');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table) {
            $table->dropColumn('permitir_aluno_editar_medidas');
        });
    }
};

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
        Schema::table('contas', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('id_aula_turma')->nullable()->after('id_matricula');
            $blueprint->foreign('id_aula_turma')->references('id')->on('aulas_turmas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contas', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['id_aula_turma']);
            $blueprint->dropColumn('id_aula_turma');
        });
    }
};

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
        Schema::table('matriculas', function (Blueprint $table) {
            // Torna id_turma nullable para suportar matrículas em curso
            $table->unsignedBigInteger('id_turma')->nullable()->change();

            // Tipo da matrícula: turma (padrão) ou curso
            $table->enum('tipo', ['turma', 'curso'])->default('turma')->after('id_turma');

            // Novos campos para matrícula em curso
            $table->unsignedBigInteger('id_curso')->nullable()->after('tipo');
            $table->unsignedBigInteger('id_nivel')->nullable()->after('id_curso');
            $table->unsignedBigInteger('id_professor')->nullable()->after('id_nivel');

            $table->foreign('id_curso')->references('id')->on('cursos');
            $table->foreign('id_nivel')->references('id')->on('niveis');
            $table->foreign('id_professor')->references('id')->on('professores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['id_curso']);
            $table->dropForeign(['id_nivel']);
            $table->dropForeign(['id_professor']);

            $table->dropColumn(['tipo', 'id_curso', 'id_nivel', 'id_professor']);

            $table->unsignedBigInteger('id_turma')->nullable(false)->change();
        });
    }
};

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
        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->foreignId('id_curso')->nullable()->after('id_turma')->constrained('cursos');
            $table->foreignId('id_nivel')->nullable()->after('id_curso')->constrained('niveis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->dropForeign(['id_curso']);
            $table->dropForeign(['id_nivel']);
            $table->dropColumn(['id_curso', 'id_nivel']);
        });
    }
};

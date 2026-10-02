<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->enum('tipo', ['regular', 'reposicao', 'reforco', 'extra'])->default('regular')->after('status');
            $table->unsignedBigInteger('id_aluno_especifico')->nullable()->after('tipo');

            $table->foreign('id_aluno_especifico')->references('id')->on('alunos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aulas_turmas', function (Blueprint $table) {
            //
        });
    }
};

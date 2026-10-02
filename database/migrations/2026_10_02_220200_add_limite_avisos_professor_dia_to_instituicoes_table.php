<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('limite_avisos_professor_dia')->nullable()->after('permissoes_professor_aulas');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn('limite_avisos_professor_dia');
        });
    }
};

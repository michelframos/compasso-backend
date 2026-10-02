<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->json('permissoes_professor_aulas')->nullable()->after('modulos_desativados');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn('permissoes_professor_aulas');
        });
    }
};

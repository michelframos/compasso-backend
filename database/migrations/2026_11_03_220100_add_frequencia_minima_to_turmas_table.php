<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->decimal('frequencia_minima', 5, 2)->nullable()->after('maximo_alunos');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->dropColumn('frequencia_minima');
        });
    }
};

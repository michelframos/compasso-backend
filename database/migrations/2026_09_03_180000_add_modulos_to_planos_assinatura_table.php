<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_assinatura', function (Blueprint $table): void {
            $table->json('modulos')->nullable()->after('limite_alunos');
        });
    }

    public function down(): void
    {
        Schema::table('planos_assinatura', function (Blueprint $table): void {
            $table->dropColumn('modulos');
        });
    }
};

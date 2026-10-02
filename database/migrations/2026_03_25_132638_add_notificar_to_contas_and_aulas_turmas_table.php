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
        Schema::table('contas', function (Blueprint $table) {
            $table->boolean('notificar')->default(true)->after('tipo');
        });

        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->boolean('notificar')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->dropColumn('notificar');
        });

        Schema::table('aulas_turmas', function (Blueprint $table) {
            $table->dropColumn('notificar');
        });
    }
};

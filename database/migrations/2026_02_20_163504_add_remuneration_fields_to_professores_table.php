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
        Schema::table('professores', function (Blueprint $table) {
            $table->decimal('salario_fixo', 10, 2)->nullable()->default(0)->after('id_usuario');
            $table->decimal('valor_hora_aula', 10, 2)->nullable()->default(0)->after('salario_fixo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professores', function (Blueprint $table) {
            $table->dropColumn(['salario_fixo', 'valor_hora_aula']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrumentos', function (Blueprint $table) {
            $table->date('data_prevista_devolucao')->nullable()->after('data_emprestimo');
        });
    }

    public function down(): void
    {
        Schema::table('instrumentos', function (Blueprint $table) {
            $table->dropColumn('data_prevista_devolucao');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->string('inicio_parcelas_mensalidade', 20)
                ->nullable()
                ->after('quantidade_parcelas_mensalidade');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table): void {
            $table->dropColumn('inicio_parcelas_mensalidade');
        });
    }
};

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
        Schema::table('turmas', function (Blueprint $table) {
            $table->enum('tipo_agendamento', ['datas', 'quantidade'])->default('datas')->after('status');
            $table->date('data_inicio')->nullable()->after('tipo_agendamento');
            $table->date('data_fim')->nullable()->after('data_inicio');
            $table->integer('quantidade_aulas')->nullable()->after('data_fim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            //
        });
    }
};

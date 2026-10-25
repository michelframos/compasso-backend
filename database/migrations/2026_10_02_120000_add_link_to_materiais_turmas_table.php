<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materiais_turmas', function (Blueprint $table) {
            $table->string('link', 2048)->nullable()->after('file_type');
            $table->string('file_type', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('materiais_turmas', function (Blueprint $table) {
            $table->dropColumn('link');
            $table->string('file_type', 50)->nullable()->change();
        });
    }
};

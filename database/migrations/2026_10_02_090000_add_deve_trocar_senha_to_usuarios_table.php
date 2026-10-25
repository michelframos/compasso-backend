<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios', 'deve_trocar_senha')) {
            Schema::table('usuarios', function (Blueprint $table): void {
                $table->boolean('deve_trocar_senha')->default(false)->after('senha');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'deve_trocar_senha')) {
            Schema::table('usuarios', function (Blueprint $table): void {
                $table->dropColumn('deve_trocar_senha');
            });
        }
    }
};

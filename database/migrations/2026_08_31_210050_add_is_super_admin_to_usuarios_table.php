<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios', 'is_super_admin')) {
            Schema::table('usuarios', function (Blueprint $table): void {
                $table->boolean('is_super_admin')->default(false)->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'is_super_admin')) {
            Schema::table('usuarios', function (Blueprint $table): void {
                $table->dropColumn('is_super_admin');
            });
        }
    }
};

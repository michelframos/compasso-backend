<?php

use Database\Seeders\InstituicaoDefaultSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new InstituicaoDefaultSeeder)->run();
    }

    public function down(): void
    {
        // Dados de seed não são revertidos automaticamente.
    }
};

<?php

namespace Database\Seeders;

use App\Modules\Core\Support\InstituicaoDataMigrator;
use Illuminate\Database\Seeder;

class InstituicaoDefaultSeeder extends Seeder
{
    public function run(): void
    {
        (new InstituicaoDataMigrator)->migrate();
    }
}

<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'super@compasso.local'],
            [
                'nome' => 'Super Admin Platform',
                'senha' => 'password',
                'role' => 'admin',
                'is_super_admin' => true,
                'cpf' => '00000000000',
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info('Platform: super@compasso.local / password');
    }
}

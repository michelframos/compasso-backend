<?php

namespace Database\Factories;

use App\Models\Responsavel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResponsavelFactory extends Factory
{
    protected $model = Responsavel::class;

    public function definition(): array
    {
        return [
            'id_usuario' => User::factory(),
            'observacoes' => $this->faker->sentence(),
            'criado_em' => now(),
        ];
    }
}

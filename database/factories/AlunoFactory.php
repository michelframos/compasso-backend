<?php

namespace Database\Factories;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlunoFactory extends Factory
{
    protected $model = Aluno::class;

    public function definition(): array
    {
        return [
            'id_usuario' => User::factory(),
            'observacoes' => $this->faker->sentence(),
            'criado_em' => now(),
        ];
    }
}

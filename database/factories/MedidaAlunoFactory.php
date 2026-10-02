<?php

namespace Database\Factories;

use App\Models\MedidaAluno;
use App\Models\Aluno;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedidaAlunoFactory extends Factory
{
    protected $model = MedidaAluno::class;

    public function definition(): array
    {
        return [
            'id_aluno' => Aluno::factory(),
            'medida_torax' => $this->faker->randomFloat(1, 60, 120),
            'medida_cintura' => $this->faker->randomFloat(1, 50, 110),
            'medida_quadril' => $this->faker->randomFloat(1, 70, 130),
            'medida_altura' => $this->faker->randomFloat(1, 150, 200),
            'ativo' => true,
            'criado_em' => now(),
        ];
    }
}

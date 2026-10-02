<?php

namespace Database\Factories;

use App\Models\Matricula;
use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    public function definition(): array
    {
        return [
            'id_aluno' => Aluno::factory(),
            'id_turma' => Turma::factory(),
            'data' => $this->faker->date(),
            'status' => $this->faker->randomElement(['ativa', 'pausada', 'cancelada', 'completada', 'transferida']),
            'observacoes' => $this->faker->sentence(),
        ];
    }
}

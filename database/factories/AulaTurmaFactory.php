<?php

namespace Database\Factories;

use App\Models\AulaTurma;
use App\Models\Turma;
use App\Models\Professor;
use Illuminate\Database\Eloquent\Factories\Factory;

class AulaTurmaFactory extends Factory
{
    protected $model = AulaTurma::class;

    public function definition(): array
    {
        return [
            'id_turma' => Turma::factory(),
            'id_professor' => Professor::factory(),
            'data' => $this->faker->date(),
            'hora_inicio' => $this->faker->time('H:i'),
            'hora_termino' => $this->faker->time('H:i'),
            'status' => $this->faker->randomElement(['agendada', 'concluida', 'cancelada']),
            'conteudo_dado' => $this->faker->sentence(),
        ];
    }
}

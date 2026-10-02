<?php

namespace Database\Factories;

use App\Models\TurmaHorario;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class TurmaHorarioFactory extends Factory
{
    protected $model = TurmaHorario::class;

    public function definition(): array
    {
        return [
            'id_turma' => Turma::factory(),
            'dia_semana' => $this->faker->randomElement(['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']),
            'hora_inicio' => $this->faker->time('H:i'),
            'hora_termino' => $this->faker->time('H:i'),
        ];
    }
}

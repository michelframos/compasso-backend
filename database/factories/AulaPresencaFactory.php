<?php

namespace Database\Factories;

use App\Models\AulaPresenca;
use App\Models\AulaTurma;
use App\Models\Aluno;
use Illuminate\Database\Eloquent\Factories\Factory;

class AulaPresencaFactory extends Factory
{
    protected $model = AulaPresenca::class;

    public function definition(): array
    {
        return [
            'id_aula_turma' => AulaTurma::factory(),
            'id_aluno' => Aluno::factory(),
            'status' => $this->faker->randomElement(['presente', 'ausente', 'justificado']),
        ];
    }
}

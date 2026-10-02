<?php

namespace Database\Factories;

use App\Models\Turma;
use App\Models\Curso;
use App\Models\Nivel;
use App\Models\Professor;
use App\Enums\TurmaStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TurmaFactory extends Factory
{
    protected $model = Turma::class;

    public function definition(): array
    {
        return [
            'id_curso' => Curso::factory(),
            'id_nivel' => Nivel::factory(),
            'id_professor' => Professor::factory(),
            'maximo_alunos' => $this->faker->numberBetween(5, 20),
            'descricao' => $this->faker->sentence(),
            'observacoes' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(TurmaStatus::cases()),
            'valor_mensalidade' => $this->faker->randomFloat(2, 100, 500),
        ];
    }
}

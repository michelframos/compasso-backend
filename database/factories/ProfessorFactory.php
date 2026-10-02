<?php

namespace Database\Factories;

use App\Models\Professor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfessorFactory extends Factory
{
    protected $model = Professor::class;

    public function definition(): array
    {
        return [
            'id_usuario' => User::factory(),
            'comissao' => $this->faker->randomFloat(2, 5, 20),
            'salario_fixo' => $this->faker->randomFloat(2, 2000, 5000),
            'valor_hora_aula' => $this->faker->randomFloat(2, 40, 100),
            'observacoes' => $this->faker->sentence(),
            'criado_em' => now(),
        ];
    }
}

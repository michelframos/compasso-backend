<?php

namespace Database\Factories;

use App\Models\ApresentacaoAluno;
use App\Models\Apresentacao;
use App\Models\Aluno;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApresentacaoAlunoFactory extends Factory
{
    protected $model = ApresentacaoAluno::class;

    public function definition(): array
    {
        return [
            'id_apresentacao' => Apresentacao::factory(),
            'id_aluno' => Aluno::factory(),
            'tamanho_figurino' => $this->faker->randomElement(['P', 'M', 'G', 'GG']),
            'pago_figurino' => $this->faker->boolean(),
            'presenca_ensaio_geral' => $this->faker->boolean(),
        ];
    }
}

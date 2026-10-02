<?php

namespace Database\Factories;

use App\Models\Apresentacao;
use App\Models\Espetaculo;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApresentacaoFactory extends Factory
{
    protected $model = Apresentacao::class;

    public function definition(): array
    {
        return [
            'id_espetaculo' => Espetaculo::factory(),
            'id_turma' => Turma::factory(),
            'titulo_musica' => $this->faker->words(2, true),
            'ordem_entrada' => $this->faker->numberBetween(1, 10),
            'duracao_estimada' => $this->faker->time('H:i'),
        ];
    }
}

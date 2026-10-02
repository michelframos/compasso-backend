<?php

namespace Database\Factories;

use App\Models\Espetaculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EspetaculoFactory extends Factory
{
    protected $model = Espetaculo::class;

    public function definition(): array
    {
        return [
            'titulo' => $this->faker->words(3, true),
            'data_evento' => $this->faker->date(),
            'local' => $this->faker->address(),
            'status' => $this->faker->randomElement(['planejamento', 'ensaios', 'concluido', 'cancelado']),
            'observacoes' => $this->faker->sentence(),
        ];
    }
}

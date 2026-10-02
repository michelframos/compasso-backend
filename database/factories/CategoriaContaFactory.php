<?php

namespace Database\Factories;

use App\Models\CategoriaConta;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoriaContaFactory extends Factory
{
    protected $model = CategoriaConta::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->word(),
            'tipo' => $this->faker->randomElement(['receita', 'despesa']),
            'descricao' => $this->faker->sentence(),
        ];
    }
}

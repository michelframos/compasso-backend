<?php

namespace Database\Factories;

use App\Models\Conta;
use App\Models\CategoriaConta;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContaFactory extends Factory
{
    protected $model = Conta::class;

    public function definition(): array
    {
        return [
            'id_categoria' => CategoriaConta::factory(),
            'descricao' => $this->faker->sentence(),
            'valor' => $this->faker->randomFloat(2, 50, 1000),
            'data_vencimento' => $this->faker->date(),
            'status' => $this->faker->randomElement(['pendente', 'pago', 'vencido', 'cancelado']),
            'tipo' => $this->faker->randomElement(['receita', 'despesa']),
            'observacoes' => $this->faker->sentence(),
        ];
    }
}

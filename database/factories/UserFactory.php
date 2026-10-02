<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'senha' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'cpf' => $this->generateCpf(),
            'role' => 'aluno',
            'foto' => null,
            'data_aniversario' => fake()->date(),
            'observacoes' => fake()->sentence(),
            'rua' => fake()->streetName(),
            'numero' => fake()->buildingNumber(),
            'complemento' => null,
            'cep' => fake()->postcode(),
            'id_estado' => null,
            'id_cidade' => null,
            'telefone' => fake()->phoneNumber(),
            'whatsapp' => fake()->phoneNumber(),
        ];
    }

    private function generateCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);

        $d1 = 0;
        for ($i = 0, $x = 10; $i < 9; $i++, $x--) $d1 += $n[$i] * $x;
        $d1 = ($d1 % 11) < 2 ? 0 : 11 - ($d1 % 11);
        $n[9] = $d1;

        $d2 = 0;
        for ($i = 0, $x = 11; $i < 10; $i++, $x--) $d2 += $n[$i] * $x;
        $d2 = ($d2 % 11) < 2 ? 0 : 11 - ($d2 % 11);
        $n[10] = $d2;

        return implode('', $n);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

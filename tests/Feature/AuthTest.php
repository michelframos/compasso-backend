<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_register_creates_user_and_profile()
    {
        // 1. Gera um CPF válido (algoritmo simples)
        $cpf = $this->generateCpf();

        $response = $this->postJson('/api/register', [
            'nome' => 'Teste User',
            'email' => 'teste_' . uniqid() . '@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'cpf' => $cpf,
            'role' => 'professor',
            'telefone' => '11999999999',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['id', 'nome', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('usuarios', ['email' => $response->json('data.email')]);
        $this->assertDatabaseHas('professores', ['id_usuario' => $response->json('data.id')]);
    }

    public function test_login_returns_token()
    {
        $cpf = $this->generateCpf();

        $user = User::create([ // Create manually to ensure valid CPF
            'nome' => 'Login User',
            'email' => 'login_' . uniqid() . '@example.com',
            'senha' => \Illuminate\Support\Facades\Hash::make('password'),
            'cpf' => $cpf,
            'role' => 'aluno',
        ]);
        // Factory would be nicer but need complex CPF generator in factory logic.
        // Manual creation works for this test.

        $instituicao = Instituicao::where('slug', 'default')->first();
        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'aluno',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'token']);
    }

    private function generateCpf() {
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
}

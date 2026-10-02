<?php

namespace App\Modules\Pessoas\UseCases\Professor;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Facades\DB;

class CreateProfessorUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(array $data): Professor
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'nome' => $data['nome'],
                'email' => $data['email'],
                'cpf' => $data['cpf'] ?? null,
                'role' => 'professor',
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'password' => $data['password'],
            ]);

            return Professor::create([
                'id_usuario' => $user->id,
                'comissao' => $data['comissao'] ?? 0,
                'salario_fixo' => $data['salario_fixo'] ?? 0,
                'valor_hora_aula' => $data['valor_hora_aula'] ?? 0,
                'observacoes' => $data['observacoes'] ?? null,
            ])->load('usuario');
        });
    }
}

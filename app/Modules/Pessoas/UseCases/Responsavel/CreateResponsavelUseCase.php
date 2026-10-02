<?php

namespace App\Modules\Pessoas\UseCases\Responsavel;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Support\Facades\DB;

class CreateResponsavelUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(array $data): Responsavel
    {
        return DB::transaction(function () use ($data) {
            $userPayload = [
                'nome' => $data['nome'],
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'role' => 'responsavel',
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'cep' => $data['cep'] ?? null,
                'rua' => $data['rua'] ?? null,
                'numero' => $data['numero'] ?? null,
                'complemento' => $data['complemento'] ?? null,
                'bairro' => $data['bairro'] ?? null,
                'id_estado' => $data['id_estado'] ?? null,
                'id_cidade' => $data['id_cidade'] ?? null,
            ];

            if (! empty($data['password'])) {
                $userPayload['password'] = $data['password'];
            } else {
                $userPayload['senha'] = null;
            }

            $user = $this->users->create($userPayload);

            return Responsavel::create([
                'id_usuario' => $user->id,
                'observacoes' => $data['observacoes'] ?? null,
            ])->load('usuario');
        });
    }
}

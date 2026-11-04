<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;
use Illuminate\Support\Arr;

class AtualizarPerfilUseCase
{
    private const CAMPOS_EDITAVEIS = [
        'nome', 'telefone', 'whatsapp', 'data_aniversario',
        'cep', 'rua', 'numero', 'complemento', 'bairro', 'id_estado', 'id_cidade',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(User $user, array $dados): User
    {
        $dados = Arr::only($dados, self::CAMPOS_EDITAVEIS);

        // Nome e data de nascimento do aluno e do responsável ficam com a secretaria.
        if (in_array($user->role, ['aluno', 'responsavel'], true)) {
            unset($dados['nome'], $dados['data_aniversario']);
        }

        return $this->users->update($user, $dados);
    }
}

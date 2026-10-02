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
        return $this->users->update($user, Arr::only($dados, self::CAMPOS_EDITAVEIS));
    }
}

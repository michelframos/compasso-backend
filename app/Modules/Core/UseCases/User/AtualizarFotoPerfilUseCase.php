<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;
use Illuminate\Http\UploadedFile;

class AtualizarFotoPerfilUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RemoverFotoPerfilUseCase $remover,
    ) {}

    public function execute(User $user, UploadedFile $foto): User
    {
        $this->remover->apagarArquivo($user);

        return $this->users->update($user, [
            'foto' => $foto->store('usuarios/fotos', RemoverFotoPerfilUseCase::DISCO),
        ]);
    }
}

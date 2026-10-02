<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RemoverFotoPerfilUseCase
{
    public const DISCO = 'public';

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(User $user): User
    {
        $this->apagarArquivo($user);

        return $this->users->update($user, ['foto' => null]);
    }

    /** Fotos legadas guardadas como URL externa não pertencem ao disco local. */
    public function apagarArquivo(User $user): void
    {
        $caminho = $user->foto;

        if ($caminho && ! Str::startsWith($caminho, ['http://', 'https://']) && Storage::disk(self::DISCO)->exists($caminho)) {
            Storage::disk(self::DISCO)->delete($caminho);
        }
    }
}

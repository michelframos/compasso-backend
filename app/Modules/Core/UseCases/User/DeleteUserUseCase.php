<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(int|string $id): void
    {
        $user = $this->users->findById($id);
        $this->users->delete($user);
    }
}

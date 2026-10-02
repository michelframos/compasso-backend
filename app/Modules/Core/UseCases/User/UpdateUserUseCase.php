<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;

class UpdateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(User $user, array $data): User
    {
        return $this->users->update($user, $data);
    }
}

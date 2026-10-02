<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(array $data): User
    {
        return $this->users->create($data);
    }
}

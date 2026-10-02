<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contrato público do Core para criação/consulta de usuários (Fase 4+).
 */
interface UserRepositoryInterface
{
    public function paginate(?string $search = null, array $roles = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int|string $id): User;

    public function findByEmail(string $email): ?User;

    public function findByCpf(string $cpf): ?User;

    public function findByCpfOrEmail(?string $cpf, ?string $email): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;
}

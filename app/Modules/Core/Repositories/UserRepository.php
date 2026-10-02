<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(?string $search = null, array $roles = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->scopedToInstituicao(User::query());

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($roles !== []) {
            $query->whereIn('role', $roles);
        }

        return $query->paginate($perPage);
    }

    public function findById(int|string $id): User
    {
        return $this->scopedToInstituicao(User::query())->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByCpf(string $cpf): ?User
    {
        return User::query()->where('cpf', $cpf)->first();
    }

    public function findByCpfOrEmail(?string $cpf, ?string $email): ?User
    {
        if (! filled($cpf) && ! filled($email)) {
            return null;
        }

        return User::query()
            ->where(function ($q) use ($cpf, $email) {
                if (filled($cpf)) {
                    $q->where('cpf', $cpf);
                }
                if (filled($email)) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();
    }

    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['senha'] = Hash::make($data['password']);
            unset($data['password'], $data['password_confirmation']);
        }

        $user = User::create($data);

        $this->attachToInstituicaoAtiva($user, $data['role'] ?? $user->role);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['senha'] = Hash::make($data['password']);
            unset($data['password'], $data['password_confirmation']);
        }

        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function scopedToInstituicao(Builder $query): Builder
    {
        if (! InstituicaoContext::has()) {
            return $query;
        }

        return $query->whereHas('instituicoes', function (Builder $relation): void {
            $relation->where('instituicoes.id', InstituicaoContext::id())
                ->where('instituicoes_usuarios.status', InstituicaoUsuario::STATUS_ATIVO);
        });
    }

    private function attachToInstituicaoAtiva(User $user, ?string $role = null): void
    {
        if (! InstituicaoContext::has()) {
            return;
        }

        InstituicaoUsuario::query()->firstOrCreate(
            [
                'id_instituicao' => InstituicaoContext::id(),
                'id_usuario' => $user->id,
            ],
            [
                'role' => $role ?? $user->role,
                'status' => InstituicaoUsuario::STATUS_ATIVO,
            ]
        );
    }
}

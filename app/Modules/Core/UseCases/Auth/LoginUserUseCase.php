<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoSubscriptionGuard;
use App\Modules\Core\Support\InstituicaoTokenService;
use App\Modules\Core\UseCases\Instituicao\ListUserInstituicoesUseCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class LoginUserUseCase
{
    public function __construct(
        private readonly InstituicaoTokenService $tokenService,
        private readonly ListUserInstituicoesUseCase $listInstituicoes,
        private readonly InstituicaoSubscriptionGuard $subscriptionGuard,
    ) {}

    /**
     * A instituição é identificada pelo CNPJ ou, alternativamente, pelo slug.
     *
     * @return array<string, mixed>|null
     */
    public function execute(string $email, string $password, ?string $tenantCnpj = null, ?string $tenantSlug = null): ?array
    {
        $authenticatedUsers = $this->findAuthenticatedUsers($email, $password);

        if ($authenticatedUsers->isEmpty()) {
            return null;
        }

        $instituicao = $this->findInstituicao($tenantCnpj, $tenantSlug);

        if ($instituicao === null) {
            return ['not_found' => true];
        }

        $user = $this->resolveUserLinkedToInstituicao($authenticatedUsers, $instituicao->id);

        if ($user === null) {
            return ['forbidden' => true];
        }

        $blocked = $this->subscriptionGuard->check($instituicao);
        if ($blocked !== null) {
            return ['subscription_blocked' => true, 'details' => $blocked];
        }

        return [
            'user' => $user,
            'token' => $this->tokenService->create($user, $instituicao)->plainTextToken,
            'tenant' => $instituicao,
            'tenants' => $this->listInstituicoes->execute($user),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function findAuthenticatedUsers(string $email, string $password): Collection
    {
        return User::query()
            ->where('email', $email)
            ->get()
            ->filter(fn (User $user): bool => Hash::check($password, $user->senha))
            ->values();
    }

    private function findInstituicao(?string $tenantCnpj, ?string $tenantSlug): ?Instituicao
    {
        $cnpj = Cnpj::normalize($tenantCnpj);

        if ($cnpj !== null) {
            return Instituicao::query()->where('cnpj', $cnpj)->first();
        }

        if (filled($tenantSlug)) {
            return Instituicao::query()->where('slug', $tenantSlug)->first();
        }

        return null;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function resolveUserLinkedToInstituicao(Collection $users, int $instituicaoId): ?User
    {
        foreach ($users as $user) {
            $pertence = InstituicaoUsuario::query()
                ->where('id_instituicao', $instituicaoId)
                ->where('id_usuario', $user->id)
                ->where('status', InstituicaoUsuario::STATUS_ATIVO)
                ->exists();

            if ($pertence) {
                return $user;
            }
        }

        return null;
    }
}

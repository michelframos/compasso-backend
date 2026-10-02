<?php

namespace App\Modules\Core\UseCases\Instituicao;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoSubscriptionGuard;
use App\Modules\Core\Support\InstituicaoTokenService;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Contracts\HasAbilities;

class SwitchInstituicaoUseCase
{
    public function __construct(
        private readonly InstituicaoTokenService $tokenService,
        private readonly ListUserInstituicoesUseCase $listInstituicoes,
        private readonly InstituicaoSubscriptionGuard $subscriptionGuard,
    ) {}

    /**
     * @return array{token: string, tenant: Instituicao, tenants: Collection<int, Instituicao>}|null
     */
    public function execute(User $user, string $tenantSlug, ?HasAbilities $currentToken = null): ?array
    {
        $instituicao = Instituicao::query()
            ->where('slug', $tenantSlug)
            ->where('status', Instituicao::STATUS_ATIVO)
            ->first();

        if ($instituicao === null) {
            return null;
        }

        $pertence = InstituicaoUsuario::query()
            ->where('id_instituicao', $instituicao->id)
            ->where('id_usuario', $user->id)
            ->where('status', InstituicaoUsuario::STATUS_ATIVO)
            ->exists();

        if (! $pertence) {
            return null;
        }

        $blocked = $this->subscriptionGuard->check($instituicao);
        if ($blocked !== null) {
            return ['subscription_blocked' => true, 'details' => $blocked];
        }

        if ($currentToken !== null) {
            $currentToken->delete();
        }

        $token = $this->tokenService->create($user, $instituicao)->plainTextToken;
        $tenants = $this->listInstituicoes->execute($user);

        return [
            'token' => $token,
            'tenant' => $instituicao,
            'tenants' => $tenants,
        ];
    }
}

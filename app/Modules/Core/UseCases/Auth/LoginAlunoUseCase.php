<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoSubscriptionGuard;
use App\Modules\Core\Support\InstituicaoTokenService;
use App\Modules\Core\UseCases\Instituicao\ListUserInstituicoesUseCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class LoginAlunoUseCase
{
    /** @var list<string> */
    private const PAPEIS_AREA_ALUNO = ['aluno', 'responsavel'];

    public function __construct(
        private readonly InstituicaoTokenService $tokenService,
        private readonly ListUserInstituicoesUseCase $listInstituicoes,
        private readonly InstituicaoSubscriptionGuard $subscriptionGuard,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $email, string $password, ?string $tenantSlug = null): ?array
    {
        $authenticatedUsers = $this->findAuthenticatedUsers($email, $password);

        if ($authenticatedUsers->isEmpty()) {
            return null;
        }

        $pares = $this->paresAreaAluno($authenticatedUsers);

        if ($pares->isEmpty()) {
            return ['forbidden' => true];
        }

        if (filled($tenantSlug)) {
            $par = $pares->first(
                fn (array $par): bool => $par['instituicao']->slug === $tenantSlug
            );

            if ($par === null) {
                return ['forbidden' => true];
            }

            return $this->autenticarPar($par);
        }

        if ($pares->count() === 1) {
            return $this->autenticarPar($pares->first());
        }

        return [
            'needs_tenant_selection' => true,
            'tenants' => $pares
                ->map(fn (array $par): Instituicao => $par['instituicao'])
                ->unique('id')
                ->values(),
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

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{user: User, instituicao: Instituicao}>
     */
    private function paresAreaAluno(Collection $users): Collection
    {
        $pares = collect();

        foreach ($users as $user) {
            $vinculos = InstituicaoUsuario::query()
                ->where('id_usuario', $user->id)
                ->where('status', InstituicaoUsuario::STATUS_ATIVO)
                ->whereIn('role', self::PAPEIS_AREA_ALUNO)
                ->with('instituicao')
                ->get();

            foreach ($vinculos as $vinculo) {
                $instituicao = $vinculo->instituicao;

                if ($instituicao === null || $instituicao->status !== Instituicao::STATUS_ATIVO) {
                    continue;
                }

                $pares->push([
                    'user' => $user,
                    'instituicao' => $instituicao,
                ]);
            }
        }

        return $pares->values();
    }

    /**
     * @param  array{user: User, instituicao: Instituicao}  $par
     * @return array<string, mixed>
     */
    private function autenticarPar(array $par): array
    {
        $user = $par['user'];
        $instituicao = $par['instituicao'];

        $blocked = $this->subscriptionGuard->check($instituicao);
        if ($blocked !== null) {
            return ['subscription_blocked' => true, 'details' => $blocked];
        }

        return [
            'user' => $user,
            'token' => $this->tokenService->create($user, $instituicao)->plainTextToken,
            'tenant' => $instituicao,
            'tenants' => $this->listInstituicoes->execute($user)
                ->filter(function (Instituicao $item) use ($user): bool {
                    return InstituicaoUsuario::query()
                        ->where('id_instituicao', $item->id)
                        ->where('id_usuario', $user->id)
                        ->where('status', InstituicaoUsuario::STATUS_ATIVO)
                        ->whereIn('role', self::PAPEIS_AREA_ALUNO)
                        ->exists();
                })
                ->values(),
        ];
    }
}

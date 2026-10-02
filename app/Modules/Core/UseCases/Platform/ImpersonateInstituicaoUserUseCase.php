<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlatformImpersonationLog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImpersonateInstituicaoUserUseCase
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
        private readonly InstituicaoTokenService $tokenService,
    ) {}

    /**
     * @return array{
     *     token: string,
     *     tenant: Instituicao,
     *     user: User,
     *     impersonation: array{log_id: int, super_admin: array{id: int, nome: string}}
     * }
     */
    public function execute(User $superAdmin, int $instituicaoId, int $targetUserId, Request $request): array
    {
        if (! $superAdmin->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Acesso restrito à plataforma.'],
            ]);
        }

        $instituicao = $this->instituicoes->findById($instituicaoId);
        $targetUser = User::query()->findOrFail($targetUserId);

        if ($targetUser->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user_id' => ['Não é permitido impersonar outro super-admin.'],
            ]);
        }

        $pertence = InstituicaoUsuario::query()
            ->where('id_instituicao', $instituicao->id)
            ->where('id_usuario', $targetUser->id)
            ->where('status', InstituicaoUsuario::STATUS_ATIVO)
            ->exists();

        if (! $pertence) {
            throw ValidationException::withMessages([
                'user_id' => ['Usuário não pertence a esta instituição.'],
            ]);
        }

        $accessToken = $this->tokenService->createImpersonation($targetUser, $instituicao, $superAdmin);

        $log = PlatformImpersonationLog::create([
            'id_super_admin' => $superAdmin->id,
            'id_usuario_alvo' => $targetUser->id,
            'id_instituicao' => $instituicao->id,
            'token_id' => $accessToken->accessToken->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'iniciado_em' => now(),
        ]);

        return [
            'token' => $accessToken->plainTextToken,
            'tenant' => $instituicao,
            'user' => $targetUser,
            'impersonation' => [
                'log_id' => $log->id,
                'super_admin' => [
                    'id' => $superAdmin->id,
                    'nome' => $superAdmin->nome,
                ],
            ],
        ];
    }
}

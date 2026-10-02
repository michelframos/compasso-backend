<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\PersonalAccessToken;
use App\Modules\Core\Models\PlatformImpersonationLog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StopPlatformImpersonationUseCase
{
    public function __construct(
        private readonly InstituicaoTokenService $tokenService,
    ) {}

    /**
     * @return array{token: string, user: UserResource}
     */
    public function execute(User $currentUser, Request $request): array
    {
        $currentToken = $this->resolveAccessToken($request);

        if ($currentToken === null || ! $currentToken->isImpersonating()) {
            throw ValidationException::withMessages([
                'token' => ['Nenhuma sessão de impersonação ativa.'],
            ]);
        }

        $superAdmin = User::query()->findOrFail($currentToken->impersonator_user_id);

        PlatformImpersonationLog::query()
            ->where('token_id', $currentToken->id)
            ->whereNull('encerrado_em')
            ->update(['encerrado_em' => now()]);

        $currentToken->delete();

        $platformToken = $this->tokenService->createPlatformToken($superAdmin);

        return [
            'token' => $platformToken->plainTextToken,
            'user' => new UserResource($superAdmin),
        ];
    }

    private function resolveAccessToken(Request $request): ?PersonalAccessToken
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken !== null) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken instanceof PersonalAccessToken) {
                return $accessToken;
            }
        }

        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}

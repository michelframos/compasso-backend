<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use Laravel\Sanctum\NewAccessToken;

class InstituicaoTokenService
{
    public function create(User $user, ?Instituicao $instituicao, string $name = 'auth_token'): NewAccessToken
    {
        $accessToken = $user->createToken($name);

        if ($instituicao !== null) {
            $accessToken->accessToken->update([
                'id_instituicao' => $instituicao->id,
            ]);
        }

        return $accessToken;
    }

    public function createImpersonation(
        User $targetUser,
        Instituicao $instituicao,
        User $superAdmin,
        string $name = 'impersonation_token',
    ): NewAccessToken {
        $accessToken = $targetUser->createToken($name);
        $accessToken->accessToken->update([
            'id_instituicao' => $instituicao->id,
            'impersonator_user_id' => $superAdmin->id,
        ]);

        return $accessToken;
    }

    public function createPlatformToken(User $superAdmin, string $name = 'platform_token'): NewAccessToken
    {
        return $superAdmin->createToken($name);
    }
}

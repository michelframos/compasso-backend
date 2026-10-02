<?php

namespace App\Modules\Core\UseCases\Public;

use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoTokenService;
use App\Modules\Core\UseCases\Platform\CreatePlatformInstituicaoUseCase;
use Illuminate\Validation\ValidationException;

class PublicSignupUseCase
{
    public function __construct(
        private readonly CreatePlatformInstituicaoUseCase $createInstituicao,
        private readonly InstituicaoTokenService $tokenService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, user: User, tenant: \App\Modules\Core\Models\Instituicao}
     */
    public function execute(array $data): array
    {
        if (filled($data['website'] ?? null)) {
            throw ValidationException::withMessages([
                'website' => ['Não foi possível concluir o cadastro.'],
            ]);
        }

        unset($data['website']);

        $data = $this->resolvePlano($data);
        $data['usar_trial_padrao'] = true;

        $adminEmail = $data['admin_email'];

        $instituicao = $this->createInstituicao->execute($data);

        $user = $instituicao->usuarios()->where('email', $adminEmail)->firstOrFail();

        $token = $this->tokenService->create($user, $instituicao)->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
            'tenant' => $instituicao,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolvePlano(array $data): array
    {
        if (! empty($data['id_plano_assinatura'])) {
            unset($data['plano_slug']);

            return $data;
        }

        $planoSlug = $data['plano_slug'] ?? null;
        unset($data['plano_slug']);

        if (! filled($planoSlug)) {
            return $data;
        }

        $plano = PlanoAssinatura::query()
            ->where('slug', $planoSlug)
            ->where('ativo', true)
            ->first();

        if ($plano !== null) {
            $data['id_plano_assinatura'] = $plano->id;
        }

        return $data;
    }
}

<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\SolicitacaoAssinatura;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Validation\ValidationException;

class SolicitarAssinaturaUseCase
{
    /**
     * @param  array{id_plano_assinatura: int, observacao?: ?string}  $data
     */
    public function execute(User $user, array $data): SolicitacaoAssinatura
    {
        $instituicaoId = InstituicaoContext::id();

        if ($instituicaoId === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Informe a instituição no header X-Tenant-Slug.'],
            ]);
        }

        $plano = PlanoAssinatura::query()
            ->whereKey($data['id_plano_assinatura'])
            ->where('ativo', true)
            ->first();

        if ($plano === null) {
            throw ValidationException::withMessages([
                'id_plano_assinatura' => ['Plano inválido ou indisponível.'],
            ]);
        }

        $pendente = SolicitacaoAssinatura::query()
            ->where('id_instituicao', $instituicaoId)
            ->where('status', SolicitacaoAssinatura::STATUS_PENDENTE)
            ->exists();

        if ($pendente) {
            throw ValidationException::withMessages([
                'id_plano_assinatura' => ['Já existe uma solicitação pendente para esta escola.'],
            ]);
        }

        return SolicitacaoAssinatura::query()->create([
            'id_instituicao' => $instituicaoId,
            'id_plano_assinatura' => $plano->id,
            'id_usuario' => $user->id,
            'status' => SolicitacaoAssinatura::STATUS_PENDENTE,
            'observacao' => $data['observacao'] ?? null,
        ])->load('plano');
    }
}

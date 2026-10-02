<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Models\SolicitacaoAssinatura;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

class RejeitarSolicitacaoAssinaturaUseCase
{
    public function execute(SolicitacaoAssinatura $solicitacao, User $aprovador, ?string $observacao = null): SolicitacaoAssinatura
    {
        if (! $solicitacao->isPendente()) {
            throw ValidationException::withMessages([
                'status' => ['Somente solicitações pendentes podem ser rejeitadas.'],
            ]);
        }

        $solicitacao->status = SolicitacaoAssinatura::STATUS_REJEITADA;
        $solicitacao->id_aprovado_por = $aprovador->id;
        $solicitacao->aprovado_em = now();

        if (filled($observacao)) {
            $solicitacao->observacao = $observacao;
        }

        $solicitacao->save();

        return $solicitacao->fresh(['plano', 'instituicao', 'usuario', 'aprovadoPor']);
    }
}

<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\SolicitacaoAssinatura;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AprovarSolicitacaoAssinaturaUseCase
{
    public function execute(SolicitacaoAssinatura $solicitacao, User $aprovador): SolicitacaoAssinatura
    {
        if (! $solicitacao->isPendente()) {
            throw ValidationException::withMessages([
                'status' => ['Somente solicitações pendentes podem ser aprovadas.'],
            ]);
        }

        return DB::transaction(function () use ($solicitacao, $aprovador): SolicitacaoAssinatura {
            $solicitacao->loadMissing(['plano', 'instituicao']);

            $instituicao = $solicitacao->instituicao;
            $instituicao->id_plano_assinatura = $solicitacao->id_plano_assinatura;
            $instituicao->assinatura_status = Instituicao::ASSINATURA_ACTIVE;
            $instituicao->assinatura_inicia_em = now()->toDateString();
            $instituicao->save();

            $solicitacao->status = SolicitacaoAssinatura::STATUS_APROVADA;
            $solicitacao->id_aprovado_por = $aprovador->id;
            $solicitacao->aprovado_em = now();
            $solicitacao->save();

            return $solicitacao->fresh(['plano', 'instituicao', 'usuario', 'aprovadoPor']);
        });
    }
}

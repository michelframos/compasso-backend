<?php

namespace App\Modules\Academico\UseCases\SolicitacaoAula;

use App\Modules\Academico\Models\SolicitacaoAula;
use Illuminate\Validation\ValidationException;

class CancelarSolicitacaoAulaUseCase
{
    public function execute(SolicitacaoAula $solicitacao): SolicitacaoAula
    {
        if (! $solicitacao->estaPendente()) {
            throw ValidationException::withMessages(['status' => 'Só é possível cancelar solicitações pendentes.']);
        }

        $solicitacao->update(['status' => SolicitacaoAula::STATUS_CANCELADA]);

        return $solicitacao;
    }
}

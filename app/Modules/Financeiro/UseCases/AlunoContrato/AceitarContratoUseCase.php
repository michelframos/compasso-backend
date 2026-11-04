<?php

namespace App\Modules\Financeiro\UseCases\AlunoContrato;

use App\Modules\Core\Models\User;
use App\Modules\Financeiro\Models\AlunoContrato;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AceitarContratoUseCase
{
    public function execute(AlunoContrato $contrato, User $user, ?string $ip, ?string $userAgent): AlunoContrato
    {
        if ($contrato->aceito_em !== null) {
            throw new HttpException(422, 'Este contrato já foi aceito.');
        }

        if ($contrato->contrato_gerado === null || $contrato->contrato_gerado === '') {
            throw new HttpException(422, 'Este contrato não tem conteúdo para aceitar.');
        }

        return DB::transaction(function () use ($contrato, $user, $ip, $userAgent) {
            $contrato->update([
                'aceito_em' => now(),
                'aceito_por_usuario_id' => $user->id,
                'ip_aceite' => $ip,
                'user_agent_aceite' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                'hash_conteudo' => hash('sha256', $contrato->contrato_gerado),
            ]);

            return $contrato->fresh('contrato');
        });
    }
}

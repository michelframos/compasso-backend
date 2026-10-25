<?php

namespace App\Modules\Academico\UseCases\SolicitacaoAula;

use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Services\AvisoAlteracaoAulaService;
use App\Modules\Academico\Services\RemanejamentoAulaService;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidirSolicitacaoAulaUseCase
{
    private const AJUSTES = ['data_sugerida', 'hora_inicio_sugerida', 'hora_termino_sugerida', 'id_professor_substituto', 'destino_cobranca', 'avisar_alunos'];

    public function __construct(
        private readonly RemanejamentoAulaService $remanejamento,
        private readonly AvisoAlteracaoAulaService $avisoAlteracao,
    ) {}

    /**
     * Ao aprovar, os ajustes da secretaria substituem o que o professor sugeriu e a alteração é aplicada:
     * cancelamento cancela a aula, reposição cria a nova aula e cancela a original,
     * substituição troca o professor e criação agenda a aula.
     *
     * @param  array{decisao: string, motivo_decisao?: ?string}  $dados
     */
    public function execute(SolicitacaoAula $solicitacao, array $dados, User $decisor): SolicitacaoAula
    {
        return DB::transaction(function () use ($solicitacao, $dados, $decisor): SolicitacaoAula {
            $solicitacao = SolicitacaoAula::query()->with('aula')->lockForUpdate()->findOrFail($solicitacao->id);

            if (! $solicitacao->estaPendente()) {
                throw ValidationException::withMessages(['status' => 'Esta solicitação já foi decidida.']);
            }

            $aulaGerada = null;

            if ($dados['decisao'] === SolicitacaoAula::STATUS_APROVADA) {
                $solicitacao->fill(array_intersect_key($dados, array_flip(self::AJUSTES)));
                $this->remanejamento->validar($solicitacao, true);
                $aulaGerada = $this->remanejamento->aplicar($solicitacao);
            }

            $solicitacao->fill([
                'status' => $dados['decisao'],
                'id_usuario_decisor' => $decisor->id,
                'decidido_em' => now(),
                'motivo_decisao' => $dados['motivo_decisao'] ?? null,
                'id_aula_gerada' => $aulaGerada?->id,
            ])->save();

            if ($solicitacao->status === SolicitacaoAula::STATUS_APROVADA) {
                $this->avisoAlteracao->avisar($solicitacao, $aulaGerada, $decisor);
            }

            return $solicitacao;
        });
    }
}

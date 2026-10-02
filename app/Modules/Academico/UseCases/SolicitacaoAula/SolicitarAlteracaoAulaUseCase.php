<?php

namespace App\Modules\Academico\UseCases\SolicitacaoAula;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Services\AvisoAlteracaoAulaService;
use App\Modules\Academico\Services\RemanejamentoAulaService;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarAlteracaoAulaUseCase
{
    /** Campos que cada tipo aproveita do pedido; o resto é descartado. */
    private const CAMPOS_DO_TIPO = [
        SolicitacaoAula::TIPO_CRIACAO => ['data_sugerida', 'hora_inicio_sugerida', 'hora_termino_sugerida', 'id_turma', 'id_curso', 'id_aluno_especifico', 'tipo_aula', 'motivo'],
        SolicitacaoAula::TIPO_CANCELAMENTO => ['id_aula_turma', 'motivo', 'destino_cobranca', 'avisar_alunos'],
        SolicitacaoAula::TIPO_REPOSICAO => ['id_aula_turma', 'motivo', 'data_sugerida', 'hora_inicio_sugerida', 'hora_termino_sugerida', 'destino_cobranca', 'avisar_alunos'],
        SolicitacaoAula::TIPO_SUBSTITUICAO => ['id_aula_turma', 'motivo', 'id_professor_substituto', 'avisar_alunos'],
    ];

    public function __construct(
        private readonly RemanejamentoAulaService $remanejamento,
        private readonly AvisoAlteracaoAulaService $avisoAlteracao,
    ) {}

    /**
     * Com a ação livre na escola, a alteração é aplicada na hora (solicitação já aprovada);
     * com aprovação, fica pendente para a secretaria.
     */
    public function execute(Professor $professor, array $dados): SolicitacaoAula
    {
        return DB::transaction(function () use ($professor, $dados): SolicitacaoAula {
            $tipo = $dados['tipo'];
            $campos = array_intersect_key($dados, array_flip(self::CAMPOS_DO_TIPO[$tipo]));

            $solicitacao = new SolicitacaoAula($campos + [
                'id_professor' => $professor->id,
                'tipo' => $tipo,
                'status' => SolicitacaoAula::STATUS_PENDENTE,
            ]);

            if ($solicitacao->id_aula_turma) {
                $aula = AulaTurma::query()->lockForUpdate()->findOrFail($solicitacao->id_aula_turma);
                $this->garantirSemPendente($aula);
                $solicitacao->setRelation('aula', $aula);
            }

            $livre = PermissoesProfessorAulas::modo($solicitacao->acao()) === PermissoesProfessorAulas::LIVRE;

            $this->remanejamento->validar($solicitacao, $livre);
            $solicitacao->save();

            if ($livre) {
                $aulaGerada = $this->remanejamento->aplicar($solicitacao);

                $solicitacao->update([
                    'status' => SolicitacaoAula::STATUS_APROVADA,
                    'aplicada_automaticamente' => true,
                    'decidido_em' => now(),
                    'id_aula_gerada' => $aulaGerada?->id,
                ]);

                $this->avisoAlteracao->avisar($solicitacao, $aulaGerada, $professor->usuario);
            }

            return $solicitacao;
        });
    }

    private function garantirSemPendente(AulaTurma $aula): void
    {
        $existe = SolicitacaoAula::query()
            ->where('id_aula_turma', $aula->id)
            ->where('status', SolicitacaoAula::STATUS_PENDENTE)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages(['id_aula_turma' => 'Já existe uma solicitação pendente para esta aula.']);
        }
    }
}

<?php

namespace App\Modules\Academico\UseCases\Aula;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Services\AulaSnapshotService;
use App\Modules\Core\Contracts\CriarContaAulaPort;
use App\Modules\Core\Domain\Recorrencia\RecorrenciaResolver;
use App\Modules\Core\Support\InstituicaoContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateAulaTurmaUseCase
{
    public function __construct(
        private readonly CriarContaAulaPort $criarContaAulaPort,
        private readonly RecorrenciaResolver $recorrencias,
        private readonly AulaSnapshotService $snapshots,
    ) {}

    /**
     * Cria a aula (ou uma aula por ocorrência, se recorrente) e, opcionalmente,
     * a conta a receber de cada ocorrência.
     *
     * @return list<AulaTurma>
     */
    public function execute(array $data): array
    {
        $data = $this->snapshots->aplicarSeConcluida($data);

        $recorrente = ! empty($data['recorrente']);
        $quantidade = $recorrente ? (int) ($data['quantidade_ocorrencias'] ?? 1) : 1;
        $recorrencia = $this->recorrencias->resolve($recorrente ? ($data['frequencia_ocorrencias'] ?? 'semanal') : 'semanal');

        $primeiraData = Carbon::parse($data['data']);
        $gerarConta = ! empty($data['gerar_conta']);
        $primeiroVencimento = $gerarConta && ! empty($data['data_vencimento_conta'])
            ? Carbon::parse($data['data_vencimento_conta'])
            : null;

        return DB::transaction(function () use ($data, $quantidade, $recorrencia, $primeiraData, $primeiroVencimento, $gerarConta) {
            $aulas = [];

            for ($i = 0; $i < $quantidade; $i++) {
                $dadosAula = $data;
                $dadosAula['data'] = $recorrencia->avancar($primeiraData, $i)->format('Y-m-d');

                if ($primeiroVencimento) {
                    $dadosAula['data_vencimento_conta'] = $recorrencia->avancar($primeiroVencimento, $i)->format('Y-m-d');
                }

                $aula = AulaTurma::create($dadosAula);
                $aulas[] = $aula;

                if ($gerarConta) {
                    $this->criarConta($aula, $dadosAula);
                }
            }

            return $aulas;
        });
    }

    private function criarConta(AulaTurma $aula, array $dadosAula): void
    {
        $this->criarContaAulaPort->execute([
            'id_instituicao' => InstituicaoContext::id(),
            'id_aluno' => $dadosAula['id_aluno_especifico'],
            'id_categoria' => $dadosAula['id_categoria_conta'],
            'id_aula_turma' => $aula->id,
            'descricao' => 'Agendamento de Aula ('.($dadosAula['tipo'] ?? 'aula').') - '.Carbon::parse($dadosAula['data'])->format('d/m/Y'),
            'valor' => $dadosAula['valor_conta'],
            'data_vencimento' => $dadosAula['data_vencimento_conta'],
            'notificar' => $dadosAula['notificar'] ?? true,
        ]);
    }
}

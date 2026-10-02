<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\DisponibilidadeProfessor;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\UseCases\Aula\CreateAulaTurmaUseCase;
use App\Modules\Core\Contracts\CobrancaAulaPort;
use Closure;
use Illuminate\Validation\ValidationException;

/** Regras comuns a pedir e a decidir uma solicitação de aula: validação, avisos, cobrança e execução. */
class RemanejamentoAulaService
{
    public function __construct(
        private readonly ClassSchedulingService $agenda,
        private readonly CreateAulaTurmaUseCase $criarAula,
        private readonly CobrancaAulaPort $cobrancas,
    ) {}

    /**
     * Impedimentos (422). Com $paraAplicar, exige também o que pode ficar para a secretaria decidir (o substituto).
     */
    public function validar(SolicitacaoAula $solicitacao, bool $paraAplicar): void
    {
        if ($solicitacao->tipo !== SolicitacaoAula::TIPO_CRIACAO) {
            $this->validarAulaOriginal($solicitacao);
        }

        if ($solicitacao->tipo === SolicitacaoAula::TIPO_SUBSTITUICAO) {
            $this->validarSubstituto($solicitacao, $paraAplicar);
        }

        if (in_array($solicitacao->tipo, [SolicitacaoAula::TIPO_CRIACAO, SolicitacaoAula::TIPO_REPOSICAO], true)) {
            $this->validarNovoHorario($solicitacao);
        }

        $this->validarAgenda($solicitacao);
        $this->validarDestinoCobranca($solicitacao);
    }

    /** @return list<string> pontos de atenção que não impedem a decisão */
    public function avisos(SolicitacaoAula $solicitacao): array
    {
        $alvo = $this->alvo($solicitacao);

        if ($alvo === null) {
            return [];
        }

        $avisos = $this->agenda
            ->conflitosDoProfessor($alvo['id_professor'], $alvo['data'], $alvo['inicio'], $alvo['termino'], $this->aulasIgnoradas($solicitacao))
            ->map(fn (AulaTurma $aula) => "O {$alvo['quem']} já tem aula neste horário: {$this->descrever($aula)}.")
            ->all();

        if ($this->agenda->dentroDaDisponibilidade($alvo['id_professor'], $alvo['data'], $alvo['inicio'], $alvo['termino']) === false) {
            $avisos[] = "O horário fica fora da disponibilidade cadastrada pelo {$alvo['quem']}.";
        }

        return $avisos;
    }

    /**
     * Contexto para decidir: avisos, cobrança da aula afetada e disponibilidade de quem vai dar a aula.
     *
     * @return array{avisos: list<string>, tem_cobranca: bool, proxima_aula_cobranca: ?string, disponibilidade: list<array{dia_semana: string, hora_inicio: string, hora_termino: string}>}
     */
    public function analisar(SolicitacaoAula $solicitacao): array
    {
        $temCobranca = $this->aulaComCobrancaAfetada($solicitacao) !== null;
        $proxima = $temCobranca && $solicitacao->tipo === SolicitacaoAula::TIPO_CANCELAMENTO
            ? $this->proximaAulaParaCobranca($solicitacao->aula)
            : null;
        $alvo = $this->alvo($solicitacao);
        $idProfessor = $alvo['id_professor'] ?? ($solicitacao->aula?->id_professor ?? $solicitacao->id_professor);

        return [
            'avisos' => $this->avisos($solicitacao),
            'tem_cobranca' => $temCobranca,
            'proxima_aula_cobranca' => $proxima ? $this->descrever($proxima) : null,
            'disponibilidade' => DisponibilidadeProfessor::doProfessor((int) $idProfessor)
                ->map(fn (DisponibilidadeProfessor $janela) => [
                    'dia_semana' => $janela->dia_semana,
                    'hora_inicio' => substr($janela->hora_inicio, 0, 5),
                    'hora_termino' => substr($janela->hora_termino, 0, 5),
                ])
                ->all(),
        ];
    }

    public function temCobranca(AulaTurma $aula): bool
    {
        return in_array($aula->id, $this->cobrancas->aulasComCobranca([$aula->id]), true);
    }

    /** Próxima aula agendada do mesmo aluno (ou turma), sem cobrança própria, que pode receber a cobrança. */
    public function proximaAulaParaCobranca(AulaTurma $aula): ?AulaTurma
    {
        if ($aula->id_turma === null && $aula->id_aluno_especifico === null) {
            return null;
        }

        $data = $aula->data->toDateString();

        $candidatas = AulaTurma::query()
            ->where('status', 'agendada')
            ->where('id', '!=', $aula->id)
            ->when(
                $aula->id_aluno_especifico,
                fn ($q) => $q->where('id_aluno_especifico', $aula->id_aluno_especifico),
                fn ($q) => $q->whereNull('id_aluno_especifico')
            )
            ->when(
                $aula->id_turma,
                fn ($q) => $q->where('id_turma', $aula->id_turma),
                fn ($q) => $q->whereNull('id_turma')->where('id_curso', $aula->id_curso)
            )
            ->where(fn ($q) => $q->whereDate('data', '>', $data)
                ->orWhere(fn ($mesmoDia) => $mesmoDia->whereDate('data', $data)->where('hora_inicio', '>', $aula->hora_inicio)))
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->limit(20)
            ->get();

        $comCobranca = $this->cobrancas->aulasComCobranca($candidatas->pluck('id')->all());

        return $candidatas->first(fn (AulaTurma $candidata) => ! in_array($candidata->id, $comCobranca, true));
    }

    /** Executa a solicitação já validada (chamar dentro de transação). Retorna a aula criada, se houver. */
    public function aplicar(SolicitacaoAula $solicitacao): ?AulaTurma
    {
        return match ($solicitacao->tipo) {
            SolicitacaoAula::TIPO_CANCELAMENTO => $this->cancelar($solicitacao),
            SolicitacaoAula::TIPO_REPOSICAO => $this->repor($solicitacao),
            SolicitacaoAula::TIPO_SUBSTITUICAO => $this->substituir($solicitacao),
            SolicitacaoAula::TIPO_CRIACAO => $this->criar($solicitacao),
        };
    }

    public function descrever(AulaTurma $aula): string
    {
        $aula->loadMissing(['turma.curso', 'turma.nivel', 'curso', 'aluno_especifico.usuario']);

        $titulo = $aula->turma
            ? trim(($aula->turma->curso?->nome ?? 'Turma').' '.($aula->turma->nivel?->nome ?? ''))
            : 'Aula individual'.($aula->aluno_especifico?->usuario?->nome ? ' de '.$aula->aluno_especifico->usuario->nome : '');

        return sprintf('%s em %s, %s–%s', $titulo, $aula->data->format('d/m'), substr($aula->hora_inicio, 0, 5), substr($aula->hora_termino, 0, 5));
    }

    private function validarAulaOriginal(SolicitacaoAula $solicitacao): void
    {
        $aula = $solicitacao->aula;

        if ($aula === null) {
            throw ValidationException::withMessages(['id_aula_turma' => 'A aula não existe mais.']);
        }

        if ($aula->status !== 'agendada') {
            throw ValidationException::withMessages(['id_aula_turma' => 'Só é possível alterar aulas que ainda estão agendadas.']);
        }

        if ($aula->data->lt(today())) {
            throw ValidationException::withMessages(['id_aula_turma' => 'Esta aula já passou.']);
        }
    }

    private function validarNovoHorario(SolicitacaoAula $solicitacao): void
    {
        if ($solicitacao->data_sugerida === null || ! $solicitacao->hora_inicio_sugerida || ! $solicitacao->hora_termino_sugerida) {
            throw ValidationException::withMessages(['data_sugerida' => 'Informe a data e o horário da nova aula.']);
        }

        if ($solicitacao->data_sugerida->lt(today())) {
            throw ValidationException::withMessages(['data_sugerida' => 'A nova aula não pode ser em data passada.']);
        }

        if (substr($solicitacao->hora_termino_sugerida, 0, 5) <= substr($solicitacao->hora_inicio_sugerida, 0, 5)) {
            throw ValidationException::withMessages(['hora_termino_sugerida' => 'O término deve ser depois do início.']);
        }
    }

    private function validarSubstituto(SolicitacaoAula $solicitacao, bool $paraAplicar): void
    {
        if ($solicitacao->id_professor_substituto === null) {
            if ($paraAplicar) {
                throw ValidationException::withMessages(['id_professor_substituto' => 'Escolha o professor substituto.']);
            }

            return;
        }

        if ((int) $solicitacao->id_professor_substituto === (int) $solicitacao->aula->id_professor) {
            throw ValidationException::withMessages(['id_professor_substituto' => 'O substituto precisa ser outro professor.']);
        }
    }

    private function validarAgenda(SolicitacaoAula $solicitacao): void
    {
        $alvo = $this->alvo($solicitacao);

        if ($alvo === null) {
            return;
        }

        $conflito = $this->agenda
            ->conflitosDoProfessor($alvo['id_professor'], $alvo['data'], $alvo['inicio'], $alvo['termino'], $this->aulasIgnoradas($solicitacao))
            ->first();

        if ($conflito !== null) {
            throw ValidationException::withMessages([
                $alvo['campo'] => "O {$alvo['quem']} já tem aula neste horário: {$this->descrever($conflito)}.",
            ]);
        }
    }

    private function validarDestinoCobranca(SolicitacaoAula $solicitacao): void
    {
        $origem = $this->aulaComCobrancaAfetada($solicitacao);

        if ($origem === null) {
            return;
        }

        if ($solicitacao->destino_cobranca === null) {
            throw ValidationException::withMessages([
                'destino_cobranca' => 'Esta aula tem cobrança: escolha se ela vai para a próxima aula ou se é cancelada.',
            ]);
        }

        if ($solicitacao->tipo === SolicitacaoAula::TIPO_CANCELAMENTO
            && $solicitacao->destino_cobranca === SolicitacaoAula::COBRANCA_PROXIMA_AULA
            && $this->proximaAulaParaCobranca($origem) === null) {
            throw ValidationException::withMessages([
                'destino_cobranca' => 'Não há próxima aula agendada deste aluno sem cobrança. Escolha cancelar a cobrança.',
            ]);
        }
    }

    /** Cancelamento e reposição tiram a aula original da agenda; se ela tem cobrança, é preciso decidir o destino. */
    private function aulaComCobrancaAfetada(SolicitacaoAula $solicitacao): ?AulaTurma
    {
        if ($solicitacao->aula === null
            || ! in_array($solicitacao->tipo, [SolicitacaoAula::TIPO_CANCELAMENTO, SolicitacaoAula::TIPO_REPOSICAO], true)) {
            return null;
        }

        return $this->temCobranca($solicitacao->aula) ? $solicitacao->aula : null;
    }

    /**
     * Professor e horário que a solicitação passa a ocupar.
     *
     * @return array{id_professor: int, data: string, inicio: string, termino: string, campo: string, quem: string}|null
     */
    private function alvo(SolicitacaoAula $solicitacao): ?array
    {
        $aula = $solicitacao->aula;

        return match ($solicitacao->tipo) {
            SolicitacaoAula::TIPO_REPOSICAO => [
                'id_professor' => (int) $aula->id_professor,
                'data' => $solicitacao->data_sugerida->toDateString(),
                'inicio' => $solicitacao->hora_inicio_sugerida,
                'termino' => $solicitacao->hora_termino_sugerida,
                'campo' => 'data_sugerida',
                'quem' => 'professor',
            ],
            SolicitacaoAula::TIPO_CRIACAO => [
                'id_professor' => (int) $solicitacao->id_professor,
                'data' => $solicitacao->data_sugerida->toDateString(),
                'inicio' => $solicitacao->hora_inicio_sugerida,
                'termino' => $solicitacao->hora_termino_sugerida,
                'campo' => 'data_sugerida',
                'quem' => 'professor',
            ],
            SolicitacaoAula::TIPO_SUBSTITUICAO => $solicitacao->id_professor_substituto === null ? null : [
                'id_professor' => (int) $solicitacao->id_professor_substituto,
                'data' => $aula->data->toDateString(),
                'inicio' => $aula->hora_inicio,
                'termino' => $aula->hora_termino,
                'campo' => 'id_professor_substituto',
                'quem' => 'substituto',
            ],
            default => null,
        };
    }

    /** @return list<int> */
    private function aulasIgnoradas(SolicitacaoAula $solicitacao): array
    {
        return $solicitacao->id_aula_turma ? [(int) $solicitacao->id_aula_turma] : [];
    }

    private function cancelar(SolicitacaoAula $solicitacao): ?AulaTurma
    {
        $aula = $solicitacao->aula;

        $this->destinarCobranca($solicitacao, $aula, fn () => $this->proximaAulaParaCobranca($aula));
        $aula->update(['status' => 'cancelada']);

        return null;
    }

    private function repor(SolicitacaoAula $solicitacao): AulaTurma
    {
        $aula = $solicitacao->aula;

        [$reposicao] = $this->criarAula->execute([
            'id_turma' => $aula->id_turma,
            'id_curso' => $aula->id_curso,
            'id_nivel' => $aula->id_nivel,
            'id_professor' => $aula->id_professor,
            'id_aluno_especifico' => $aula->id_aluno_especifico,
            'data' => $solicitacao->data_sugerida->toDateString(),
            'hora_inicio' => substr($solicitacao->hora_inicio_sugerida, 0, 5),
            'hora_termino' => substr($solicitacao->hora_termino_sugerida, 0, 5),
            'status' => 'agendada',
            'tipo' => 'reposicao',
            'notificar' => $aula->notificar ?? true,
        ]);

        $this->destinarCobranca($solicitacao, $aula, fn () => $reposicao);
        $aula->update(['status' => 'cancelada']);

        return $reposicao;
    }

    private function substituir(SolicitacaoAula $solicitacao): ?AulaTurma
    {
        $solicitacao->aula->update(['id_professor' => $solicitacao->id_professor_substituto]);

        return null;
    }

    private function criar(SolicitacaoAula $solicitacao): AulaTurma
    {
        $turma = $solicitacao->turma;

        [$aula] = $this->criarAula->execute([
            'id_turma' => $solicitacao->id_turma,
            'id_curso' => $turma?->id_curso ?? $solicitacao->id_curso,
            'id_nivel' => $turma?->id_nivel,
            'id_professor' => $solicitacao->id_professor,
            'id_aluno_especifico' => $solicitacao->id_aluno_especifico,
            'data' => $solicitacao->data_sugerida->toDateString(),
            'hora_inicio' => substr($solicitacao->hora_inicio_sugerida, 0, 5),
            'hora_termino' => substr($solicitacao->hora_termino_sugerida, 0, 5),
            'status' => 'agendada',
            'tipo' => $solicitacao->tipo_aula ?? 'extra',
        ]);

        return $aula;
    }

    private function destinarCobranca(SolicitacaoAula $solicitacao, AulaTurma $aula, Closure $destino): void
    {
        if (! $this->temCobranca($aula)) {
            return;
        }

        $observacao = "Aula de {$aula->data->format('d/m/Y')} cancelada pela solicitação #{$solicitacao->id}.";

        if ($solicitacao->destino_cobranca === SolicitacaoAula::COBRANCA_CANCELAR) {
            if (! $this->cobrancas->cancelar($aula->id, $observacao)) {
                throw ValidationException::withMessages([
                    'destino_cobranca' => 'A cobrança desta aula já recebeu pagamento e não pode ser cancelada. Transfira para a próxima aula.',
                ]);
            }

            return;
        }

        $proxima = $destino();

        if ($proxima === null) {
            throw ValidationException::withMessages([
                'destino_cobranca' => 'Não há próxima aula agendada deste aluno sem cobrança. Escolha cancelar a cobrança.',
            ]);
        }

        $this->cobrancas->transferir($aula->id, $proxima->id, "{$observacao} Cobrança transferida para a aula de {$proxima->data->format('d/m/Y')}.");
    }
}

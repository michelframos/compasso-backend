<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Core\Contracts\ProximasApresentacoesAlunoPort;
use App\Modules\Core\Contracts\ResumoFinanceiroAlunoPort;
use App\Modules\Pessoas\Models\Aluno;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PainelAlunoService
{
    public const LIMITE_AULAS = 5;

    public const LIMITE_AVISOS = 5;

    public const JANELA_AVISOS_DIAS = 30;

    private const DIAS = [
        'domingo' => CarbonInterface::SUNDAY,
        'segunda' => CarbonInterface::MONDAY,
        'terca' => CarbonInterface::TUESDAY,
        'quarta' => CarbonInterface::WEDNESDAY,
        'quinta' => CarbonInterface::THURSDAY,
        'sexta' => CarbonInterface::FRIDAY,
        'sabado' => CarbonInterface::SATURDAY,
    ];

    public function __construct(
        private readonly ResumoFinanceiroAlunoPort $financeiro,
        private readonly ProximasApresentacoesAlunoPort $apresentacoes,
    ) {}

    /**
     * @return array{
     *     data_referencia: string,
     *     proxima_aula: array<string, mixed>|null,
     *     proximas_aulas: list<array<string, mixed>>,
     *     financeiro: array<string, mixed>,
     *     apresentacao: array{disponivel: bool, proxima: array<string, mixed>|null},
     *     avisos: list<array<string, mixed>>
     * }
     */
    public function montar(Aluno $aluno, ?CarbonInterface $agora = null): array
    {
        $agora = CarbonImmutable::instance($agora ?? now());
        $aulas = $this->proximasAulas($aluno->id, $agora);
        $apresentacoes = $this->apresentacoes->listar($aluno->id, 1);

        return [
            'data_referencia' => $agora->toDateString(),
            'proxima_aula' => $aulas[0] ?? $this->proximaPeloHorario($aluno->id, $agora),
            'proximas_aulas' => $aulas,
            'financeiro' => $this->financeiro->resumir($aluno->id),
            'apresentacao' => [
                'disponivel' => $apresentacoes['disponivel'],
                'proxima' => $apresentacoes['itens'][0] ?? null,
            ],
            'avisos' => $this->avisos($aluno->id, $agora),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function proximasAulas(int $idAluno, CarbonImmutable $agora): array
    {
        $hoje = $agora->toDateString();

        return $this->aulasDoAluno($idAluno)
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelada'))
            ->where(fn (Builder $q) => $q
                ->where('data', '>', $hoje)
                ->orWhere(fn (Builder $h) => $h->where('data', $hoje)->where('hora_termino', '>', $agora->format('H:i:s'))))
            ->with(['turma.curso', 'turma.nivel', 'curso'])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->limit(self::LIMITE_AULAS)
            ->get()
            ->map(fn (AulaTurma $aula) => $this->aula($aula))
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function proximaPeloHorario(int $idAluno, CarbonImmutable $agora): ?array
    {
        $horarios = TurmaHorario::query()
            ->whereHas('turma.matriculas', fn (Builder $m) => $m->where('id_aluno', $idAluno)->vigentes())
            ->with(['turma.curso', 'turma.nivel'])
            ->get();

        $proxima = $horarios
            ->map(fn (TurmaHorario $horario) => ['horario' => $horario, 'data' => $this->proximaData($horario, $agora)])
            ->sortBy(fn (array $item) => $item['data']->toDateString().' '.$item['horario']->hora_inicio)
            ->first();

        if ($proxima === null) {
            return null;
        }

        /** @var TurmaHorario $horario */
        $horario = $proxima['horario'];

        return [
            'origem' => 'horario',
            'id' => $horario->id,
            'data' => $proxima['data']->toDateString(),
            'hora_inicio' => $this->hora($horario->hora_inicio),
            'hora_termino' => $this->hora($horario->hora_termino),
            'status' => null,
            'tipo' => null,
            'turma' => $horario->turma?->apelido() ?? 'Turma',
            'dia_semana' => $horario->dia_semana,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function avisos(int $idAluno, CarbonImmutable $agora): array
    {
        $desde = $agora->subDays(self::JANELA_AVISOS_DIAS);

        $remarcacoes = SolicitacaoAula::query()
            ->where('tipo', SolicitacaoAula::TIPO_REPOSICAO)
            ->where('status', SolicitacaoAula::STATUS_APROVADA)
            ->where('decidido_em', '>=', $desde)
            ->whereHas('aula', fn (Builder $aula) => $this->restringirAoAluno($aula, $idAluno))
            ->with(['aula.turma.curso', 'aula.turma.nivel', 'aula.curso', 'aulaGerada'])
            ->orderByDesc('decidido_em')
            ->limit(self::LIMITE_AVISOS)
            ->get();

        $idsRemarcadas = $remarcacoes->pluck('id_aula_turma')->filter()->all();
        $canceladas = $this->aulasDoAluno($idAluno)
            ->where('status', 'cancelada')
            ->where('data', '>=', $desde->toDateString())
            ->when($idsRemarcadas !== [], fn (Builder $q) => $q->whereNotIn('id', $idsRemarcadas))
            ->with(['turma.curso', 'turma.nivel', 'curso'])
            ->orderByDesc('data')
            ->limit(self::LIMITE_AVISOS)
            ->get();

        return $remarcacoes
            ->map(fn (SolicitacaoAula $solicitacao) => $this->avisoRemarcada($solicitacao))
            ->concat($canceladas->map(fn (AulaTurma $aula) => $this->avisoCancelada($aula)))
            ->sortByDesc('data')
            ->take(self::LIMITE_AVISOS)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function aula(AulaTurma $aula): array
    {
        return [
            'origem' => 'aula',
            'id' => $aula->id,
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => $this->hora($aula->hora_inicio),
            'hora_termino' => $this->hora($aula->hora_termino),
            'status' => $aula->status,
            'tipo' => $aula->tipo,
            'turma' => $this->nome($aula),
            'dia_semana' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function avisoCancelada(AulaTurma $aula): array
    {
        return [
            'tipo' => 'cancelada',
            'titulo' => 'Aula cancelada',
            'mensagem' => sprintf('A aula de %s de %s foi cancelada.', $this->nome($aula), $this->quando($aula)),
            'data' => $aula->data?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function avisoRemarcada(SolicitacaoAula $solicitacao): array
    {
        $aula = $solicitacao->aula;
        $nova = $solicitacao->aulaGerada;
        $destino = $nova ? $this->quando($nova) : ($solicitacao->data_sugerida?->format('d/m') ?? 'outra data');

        return [
            'tipo' => 'remarcada',
            'titulo' => 'Aula remarcada',
            'mensagem' => sprintf(
                'A aula de %s de %s foi remarcada para %s.',
                $aula ? $this->nome($aula) : 'aula',
                $aula ? $this->quando($aula) : 'outra data',
                $destino
            ),
            'data' => $nova?->data?->toDateString() ?? $solicitacao->data_sugerida?->toDateString(),
        ];
    }

    private function aulasDoAluno(int $idAluno): Builder
    {
        return $this->restringirAoAluno(AulaTurma::query(), $idAluno);
    }

    private function restringirAoAluno(Builder $query, int $idAluno): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('id_aluno_especifico', $idAluno)
            ->orWhere(fn (Builder $turma) => $turma
                ->whereNull('id_aluno_especifico')
                ->whereHas('turma.matriculas', fn (Builder $m) => $m->where('id_aluno', $idAluno)->vigentes())));
    }

    private function proximaData(TurmaHorario $horario, CarbonImmutable $agora): CarbonImmutable
    {
        $dia = self::DIAS[$horario->dia_semana] ?? CarbonInterface::MONDAY;
        $candidato = $agora->startOfDay();

        if ($candidato->dayOfWeek !== $dia) {
            $candidato = $candidato->next($dia);
        }

        $termino = CarbonImmutable::parse($candidato->toDateString().' '.$horario->hora_termino);
        if ($termino->lessThanOrEqualTo($agora)) {
            $candidato = $candidato->addWeek();
        }

        return $candidato;
    }

    private function nome(AulaTurma $aula): string
    {
        if ($aula->turma) {
            return $aula->turma->apelido();
        }

        return $aula->curso?->nome ?? 'Aula';
    }

    private function quando(AulaTurma $aula): string
    {
        $data = $aula->data?->format('d/m') ?? '';

        return trim(sprintf('%s, %s–%s', $data, $this->hora($aula->hora_inicio), $this->hora($aula->hora_termino)));
    }

    private function hora(mixed $valor): string
    {
        return substr((string) $valor, 0, 5);
    }
}

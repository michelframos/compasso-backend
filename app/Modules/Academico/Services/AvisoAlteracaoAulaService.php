<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Core\Models\User;
use Carbon\CarbonInterface;

/**
 * Avisa alunos e responsáveis quando uma aula é cancelada, remarcada ou muda de professor.
 * Usa todos os canais disponíveis e não conta no limite diário do professor.
 */
class AvisoAlteracaoAulaService
{
    private const TIPOS = [SolicitacaoAula::TIPO_CANCELAMENTO, SolicitacaoAula::TIPO_REPOSICAO, SolicitacaoAula::TIPO_SUBSTITUICAO];

    private const DIAS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

    public function __construct(
        private readonly AvisoTurmaService $avisos,
        private readonly DestinatariosAvisoService $destinatarios,
    ) {}

    public function avisar(SolicitacaoAula $solicitacao, ?AulaTurma $aulaGerada, User $autor): ?AvisoTurma
    {
        if (! $solicitacao->avisar_alunos || ! in_array($solicitacao->tipo, self::TIPOS, true) || ! $solicitacao->id_aula_turma) {
            return null;
        }

        $aula = AulaTurma::query()
            ->with(['turma.curso', 'turma.nivel', 'curso', 'professor.usuario'])
            ->find($solicitacao->id_aula_turma);
        $canais = $this->avisos->canaisDisponiveis();
        $alunos = $aula ? $this->destinatarios->alunosDaAula($aula) : collect();

        if (! $aula || $canais === [] || $alunos->isEmpty()) {
            return null;
        }

        [$titulo, $mensagem] = $this->texto($solicitacao, $aula, $aulaGerada);

        return $this->avisos->registrar(
            $autor,
            ['titulo' => $titulo, 'mensagem' => $mensagem, 'publico' => AvisoTurma::PUBLICO_AMBOS, 'canais' => $canais],
            $alunos,
            $aula->turma,
            $aula->id_professor,
            AvisoTurma::ORIGEM_AULA_ALTERADA,
            $aula,
        );
    }

    /** @return array{0: string, 1: string} */
    private function texto(SolicitacaoAula $solicitacao, AulaTurma $aula, ?AulaTurma $aulaGerada): array
    {
        $original = sprintf('A aula de %s de %s', $this->nome($aula), $this->quando($aula->data, $aula->hora_inicio, $aula->hora_termino));

        return match ($solicitacao->tipo) {
            SolicitacaoAula::TIPO_CANCELAMENTO => ['Aula cancelada', "{$original} foi cancelada."],
            SolicitacaoAula::TIPO_REPOSICAO => ['Aula remarcada', sprintf(
                '%s foi remarcada para %s.',
                $original,
                $aulaGerada ? $this->quando($aulaGerada->data, $aulaGerada->hora_inicio, $aulaGerada->hora_termino) : 'outra data'
            )],
            default => ['Troca de professor', sprintf(
                '%s será dada pelo professor %s.',
                $original,
                $aula->professor?->usuario?->nome ?? 'substituto'
            )],
        };
    }

    private function nome(AulaTurma $aula): string
    {
        if ($aula->turma) {
            return $aula->turma->apelido();
        }

        return $aula->curso?->nome ?? 'aula individual';
    }

    private function quando(CarbonInterface $data, string $inicio, string $termino): string
    {
        return sprintf('%s, %s, das %s às %s', self::DIAS[$data->dayOfWeek], $data->format('d/m'), substr($inicio, 0, 5), substr($termino, 0, 5));
    }
}

<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\DisponibilidadeProfessor;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ClassSchedulingService
{
    public function generateClasses(Turma $turma)
    {
        if ($turma->tipo_agendamento === 'datas') {
            return $this->generateByDates($turma);
        }

        return $this->generateByQuantity($turma);
    }

    private function generateByDates(Turma $turma)
    {
        if (!$turma->data_inicio || !$turma->data_fim) {
            return;
        }

        $horarios = $turma->horarios;
        if ($horarios->isEmpty()) {
            return;
        }

        $period = CarbonPeriod::create($turma->data_inicio, $turma->data_fim);
        $diasSemanaMap = [
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'domingo' => 0,
        ];

        foreach ($period as $date) {
            foreach ($horarios as $horario) {
                if ($date->dayOfWeek === $diasSemanaMap[$horario->dia_semana]) {
                    $this->createClass($turma, $date, $horario);
                }
            }
        }
    }

    private function generateByQuantity(Turma $turma)
    {
        if (!$turma->data_inicio || !$turma->quantidade_aulas) {
            return;
        }

        $horarios = $turma->horarios->sortBy(function ($h) {
            $diasSemanaMap = [
                'segunda' => 1,
                'terca' => 2,
                'quarta' => 3,
                'quinta' => 4,
                'sexta' => 5,
                'sabado' => 6,
                'domingo' => 0,
            ];
            return $diasSemanaMap[$h->dia_semana];
        });

        if ($horarios->isEmpty()) {
            return;
        }

        $aulasCriadas = 0;
        $currentDate = Carbon::parse($turma->data_inicio);
        $maxIterations = 1000; // Segurança contra loops infinitos
        $iterations = 0;

        $diasSemanaMap = [
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'domingo' => 0,
        ];

        while ($aulasCriadas < $turma->quantidade_aulas && $iterations < $maxIterations) {
            foreach ($horarios as $horario) {
                if ($currentDate->dayOfWeek === $diasSemanaMap[$horario->dia_semana]) {
                    if ($aulasCriadas < $turma->quantidade_aulas) {
                        $this->createClass($turma, $currentDate, $horario);
                        $aulasCriadas++;
                        $ultimoDia = $currentDate->toDateString();
                    }
                }
            }
            $currentDate->addDay();
            $iterations++;
        }

        if (isset($ultimoDia) && $turma->data_fim !== $ultimoDia) {
            $turma->update(['data_fim' => $ultimoDia]);
        }
    }

    /** Aulas não canceladas do professor que se sobrepõem ao horário (encostar no limite não conta). */
    public function conflitosDoProfessor(int $idProfessor, string $data, string $inicio, string $termino, array $ignorarAulas = []): Collection
    {
        return AulaTurma::query()
            ->where('id_professor', $idProfessor)
            ->where('status', '!=', 'cancelada')
            ->whereDate('data', $data)
            ->where('hora_inicio', '<', $this->hora($termino))
            ->where('hora_termino', '>', $this->hora($inicio))
            ->when($ignorarAulas !== [], fn ($q) => $q->whereNotIn('id', $ignorarAulas))
            ->with(['turma.curso', 'turma.nivel', 'aluno_especifico.usuario'])
            ->orderBy('hora_inicio')
            ->get();
    }

    /** Aulas não canceladas do aluno que se sobrepõem ao horário, inclusive reposição já pedida. */
    public function conflitosDoAluno(int $idAluno, string $data, string $inicio, string $termino, array $ignorarAulas = []): Collection
    {
        return AulaTurma::query()
            ->where('status', '!=', 'cancelada')
            ->whereDate('data', $data)
            ->where('hora_inicio', '<', $this->hora($termino))
            ->where('hora_termino', '>', $this->hora($inicio))
            ->when($ignorarAulas !== [], fn ($q) => $q->whereNotIn('id', $ignorarAulas))
            ->where(fn ($q) => $q
                ->where('id_aluno_especifico', $idAluno)
                ->orWhere(fn ($turma) => $turma
                    ->whereNull('id_aluno_especifico')
                    ->whereHas('turma.matriculas', fn ($m) => $m->where('id_aluno', $idAluno)->vigentes()))
                ->orWhereHas('reposicoesDestino', fn ($pedido) => $pedido
                    ->where('id_aluno', $idAluno)
                    ->whereIn('status', SolicitacaoReposicao::ABERTAS)))
            ->orderBy('hora_inicio')
            ->get();
    }

    /** Vaga na turma, descontando matrículas vigentes e reposições pendentes ou aprovadas de outros alunos. */
    public function temVaga(AulaTurma $aula, int $idAluno): bool
    {
        if ($aula->id_turma === null) {
            return false;
        }

        if ($aula->id_aluno_especifico !== null && (int) $aula->id_aluno_especifico !== $idAluno) {
            return false;
        }

        $turma = $aula->relationLoaded('turma') ? $aula->turma : $aula->turma()->first();

        if ($turma === null) {
            return false;
        }

        $matriculados = $turma->matriculas()->vigentes()->pluck('id_aluno');
        $reservados = SolicitacaoReposicao::query()
            ->where('id_aula_turma_destino', $aula->id)
            ->whereIn('status', SolicitacaoReposicao::ABERTAS)
            ->where('id_aluno', '!=', $idAluno)
            ->pluck('id_aluno');

        return $matriculados->merge($reservados)->unique()->count() < (int) $turma->maximo_alunos;
    }

    /** Null quando o professor não cadastrou disponibilidade (não há o que avisar). */
    public function dentroDaDisponibilidade(int $idProfessor, string $data, string $inicio, string $termino): ?bool
    {
        $janelas = DisponibilidadeProfessor::query()->where('id_professor', $idProfessor)->get();

        if ($janelas->isEmpty()) {
            return null;
        }

        $dia = DisponibilidadeProfessor::diaDa(Carbon::parse($data));

        return $janelas->contains(fn (DisponibilidadeProfessor $janela) => $janela->dia_semana === $dia
            && $this->hora($janela->hora_inicio) <= $this->hora($inicio)
            && $this->hora($janela->hora_termino) >= $this->hora($termino));
    }

    private function hora(string $valor): string
    {
        return substr($valor, 0, 5).':00';
    }

    private function createClass(Turma $turma, Carbon $date, $horario)
    {
        // Verifica se já existe aula nesse dia/horário para evitar duplicatas em re-processamentos
        $exists = AulaTurma::where('id_turma', $turma->id)
            ->where('data', $date->toDateString())
            ->where('hora_inicio', $horario->hora_inicio)
            ->exists();

        if (!$exists) {
            AulaTurma::create([
                'id_turma' => $turma->id,
                'id_professor' => $turma->id_professor,
                'data' => $date->toDateString(),
                'hora_inicio' => $horario->hora_inicio,
                'hora_termino' => $horario->hora_termino,
                'status' => 'agendada',
                'tipo' => 'regular',
            ]);
        }
    }
}

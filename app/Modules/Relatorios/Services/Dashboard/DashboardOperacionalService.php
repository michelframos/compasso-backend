<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\AulaTurma;
use App\Models\Lead;
use App\Models\Matricula;
use App\Modules\Core\Support\InstituicaoContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardOperacionalService
{
    public function build(DashboardPeriod $period): array
    {
        return [
            'mes' => $period->monthKey(),
            'agendaHoje' => $this->agendaHoje($period->today),
            'alunosAusentes' => $this->alunosAusentes(),
            'aniversariantes' => $this->aniversariantes($period->start->month),
            'conversaoData' => $this->conversao($period),
            'distributionData' => $this->distribution(),
            'ocupacaoHorarios' => $this->ocupacaoHorarios(),
        ];
    }

    private function agendaHoje(Carbon $hoje): array
    {
        return AulaTurma::query()
            ->with([
                'curso',
                'nivel',
                'professor.usuario',
                'aluno_especifico.usuario',
                'turma' => function ($q) {
                    $q->with(['curso', 'nivel'])
                        ->withCount([
                            'matriculas as alunos_count' => function ($m) {
                                $m->whereNotIn('status', ['cancelada', 'transferida']);
                            },
                        ]);
                },
            ])
            ->whereDate('data', $hoje)
            ->orderBy('hora_inicio')
            ->get()
            ->map(function (AulaTurma $aula) {
                $alunoNome = $aula->aluno_especifico?->usuario?->nome;
                $curso = $aula->curso?->nome
                    ?? $aula->turma?->curso?->nome
                    ?? $aula->turma?->descricao;
                $nivel = $aula->nivel?->nome ?? $aula->turma?->nivel?->nome;
                $alunosCount = $alunoNome
                    ? 1
                    : (int) ($aula->turma?->alunos_count ?? 0);

                return [
                    'id' => $aula->id,
                    'turmaId' => $aula->id_turma,
                    'time' => $this->formatTime($aula->hora_inicio),
                    'horaTermino' => $this->formatTime($aula->hora_termino),
                    'course' => $curso,
                    'nivel' => $nivel,
                    'turma' => $aula->turma?->descricao,
                    'teacher' => $aula->professor?->usuario?->nome,
                    'tipo' => $aula->tipo ?: 'regular',
                    'aluno' => $alunoNome,
                    'alunosCount' => $alunosCount,
                    'status' => $aula->status ?: 'agendada',
                ];
            })
            ->values()
            ->all();
    }

    private function alunosAusentes(): array
    {
        $ranked = DB::table('aulas_presencas')
            ->join('aulas_turmas', 'aulas_presencas.id_aula_turma', '=', 'aulas_turmas.id')
            ->whereNull('aulas_presencas.deleted_at')
            ->whereNull('aulas_turmas.deleted_at')
            ->select(
                'aulas_presencas.id_aluno',
                'aulas_presencas.status',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY aulas_presencas.id_aluno ORDER BY aulas_turmas.data DESC, aulas_turmas.hora_inicio DESC) as rn')
            );

        InstituicaoContext::applyToQuery($ranked, 'aulas_turmas');

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->join('alunos', 'alunos.id', '=', 'ranked.id_aluno')
            ->join('usuarios', 'usuarios.id', '=', 'alunos.id_usuario')
            ->whereNull('alunos.deleted_at')
            ->whereNull('usuarios.deleted_at')
            ->where('ranked.rn', '<=', 2)
            ->groupBy('ranked.id_aluno', 'usuarios.nome')
            ->havingRaw('COUNT(*) = 2')
            ->havingRaw("SUM(CASE WHEN ranked.status = 'ausente' THEN 1 ELSE 0 END) = 2")
            ->orderBy('usuarios.nome')
            ->get([
                'ranked.id_aluno as id',
                'usuarios.nome as name',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
            ])
            ->values()
            ->all();
    }

    private function aniversariantes(int $mes): array
    {
        return Aluno::with('usuario')
            ->whereHas('usuario', function ($q) use ($mes) {
                $q->whereMonth('data_aniversario', $mes);
            })
            ->get()
            ->map(function (Aluno $aluno) {
                $data = $aluno->usuario?->data_aniversario;
                if (! $data) {
                    return null;
                }

                return [
                    'id' => $aluno->id,
                    'name' => $aluno->usuario->nome,
                    'day' => Carbon::parse($data)->day,
                    'type' => 'Aluno',
                ];
            })
            ->filter()
            ->sortBy('day')
            ->values()
            ->all();
    }

    private function conversao(DashboardPeriod $period): array
    {
        $inicio = $period->start->copy()->startOfDay();
        $fim = $period->end->copy()->endOfDay();

        $leads = Lead::whereBetween('created_at', [$inicio, $fim])->count();

        $porStatus = Lead::whereBetween('created_at', [$inicio, $fim])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [
            'novo' => 'Novo',
            'contatado' => 'Contatado',
            'matriculado' => 'Matriculado',
            'perdido' => 'Perdido',
        ];

        $stages = [];
        foreach ($labels as $status => $label) {
            $stages[] = [
                'name' => $status,
                'label' => $label,
                'value' => (int) ($porStatus[$status] ?? 0),
            ];
        }

        $matriculas = Matricula::whereBetween('data', [$period->start->toDateString(), $period->end->toDateString()])
            ->whereNotNull('id_lead')
            ->count();

        $rate = $leads > 0 ? round(($matriculas / $leads) * 100, 1) : 0;

        return [
            'stages' => $stages,
            'leads' => $leads,
            'matriculas' => $matriculas,
            'rate' => $rate,
        ];
    }

    private function distribution(): array
    {
        $query = DB::table('matriculas')
            ->join('turmas', 'matriculas.id_turma', '=', 'turmas.id')
            ->join('cursos', 'turmas.id_curso', '=', 'cursos.id')
            ->whereNull('matriculas.deleted_at')
            ->whereNotIn('matriculas.status', ['cancelada', 'transferida']);

        InstituicaoContext::applyToQuery($query, 'matriculas');

        return $query
            ->select('cursos.nome as name', DB::raw('count(matriculas.id) as value'))
            ->groupBy('cursos.nome')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    private function ocupacaoHorarios(): array
    {
        $dias = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
        $horas = [];
        for ($h = 7; $h <= 21; $h++) {
            $horas[] = sprintf('%02d:00', $h);
        }

        $horariosQuery = DB::table('turma_horarios')
            ->join('turmas', 'turma_horarios.id_turma', '=', 'turmas.id')
            ->whereNull('turma_horarios.deleted_at')
            ->whereNull('turmas.deleted_at')
            ->whereIn('turmas.status', [TurmaStatus::ABERTA->value, TurmaStatus::EM_ANDAMENTO->value]);

        InstituicaoContext::applyToQuery($horariosQuery, 'turma_horarios');

        $horarios = $horariosQuery->get([
                'turma_horarios.id_turma',
                'turma_horarios.dia_semana',
                'turma_horarios.hora_inicio',
                'turma_horarios.hora_termino',
                'turmas.maximo_alunos',
            ]);

        $alunosQuery = DB::table('matriculas')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelada', 'transferida'])
            ->select('id_turma', DB::raw('COUNT(DISTINCT id_aluno) as total'))
            ->groupBy('id_turma');

        InstituicaoContext::applyToQuery($alunosQuery, 'matriculas');

        $alunosPorTurma = $alunosQuery->pluck('total', 'id_turma');

        $acc = [];
        foreach ($horarios as $horario) {
            $inicioMin = $this->timeToMinutes($horario->hora_inicio);
            $fimMin = $this->timeToMinutes($horario->hora_termino);
            $alunos = (int) ($alunosPorTurma[$horario->id_turma] ?? 0);
            $capacidade = max(1, (int) $horario->maximo_alunos);

            foreach ($horas as $hora) {
                $slotMin = $this->timeToMinutes($hora);
                $slotFim = $slotMin + 60;
                if ($inicioMin < $slotFim && $fimMin > $slotMin) {
                    $key = $horario->dia_semana . '|' . $hora;
                    if (! isset($acc[$key])) {
                        $acc[$key] = ['alunos' => 0, 'capacidade' => 0, 'turmas' => 0];
                    }
                    $acc[$key]['alunos'] += $alunos;
                    $acc[$key]['capacidade'] += $capacidade;
                    $acc[$key]['turmas'] += 1;
                }
            }
        }

        $cells = [];
        foreach ($dias as $dia) {
            foreach ($horas as $hora) {
                $key = $dia . '|' . $hora;
                $cell = $acc[$key] ?? ['alunos' => 0, 'capacidade' => 0, 'turmas' => 0];
                $percentual = $cell['capacidade'] > 0
                    ? round(($cell['alunos'] / $cell['capacidade']) * 100, 1)
                    : 0;

                $cells[] = [
                    'dia' => $dia,
                    'hora' => $hora,
                    'alunos' => $cell['alunos'],
                    'capacidade' => $cell['capacidade'],
                    'turmas' => $cell['turmas'],
                    'percentual' => min(100, $percentual),
                ];
            }
        }

        return [
            'dias' => $dias,
            'horas' => $horas,
            'cells' => $cells,
        ];
    }

    private function formatTime(mixed $time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return is_string($time) ? substr($time, 0, 5) : null;
        }
    }

    private function timeToMinutes(mixed $time): int
    {
        $formatted = $this->formatTime($time) ?? '00:00';
        [$h, $m] = array_pad(explode(':', $formatted), 2, 0);

        return ((int) $h * 60) + (int) $m;
    }
}

<?php

namespace App\Modules\Relatorios\Services;

use App\Models\AulaTurma;
use App\Modules\Academico\Models\Turma;

/**
 * Diário de classe de uma turma: alunos, aulas concluídas no período, presenças e conteúdos.
 * Usado pelo relatório da secretaria e pela área do professor.
 */
class DiarioClasseService
{
    private const STATUS_PRESENCA = [
        'presente' => 'presente',
        'ausente' => 'falta',
        'justificado' => 'falta_justificada',
    ];

    public function montar(Turma $turma, string $dataInicio, string $dataFim): array
    {
        $turma->load([
            'professor.usuario',
            'curso',
            'nivel',
            'horarios',
            'matriculas' => fn ($query) => $query->whereNull('deleted_at')->with('aluno.usuario'),
        ]);

        $aulas = AulaTurma::where('id_turma', $turma->id)
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->where('status', 'concluida')
            ->with('presencas')
            ->orderBy('data', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->get();

        return [
            'id' => $turma->id,
            'descricao' => $turma->descricao,
            'curso' => $turma->curso ? $turma->curso->nome : 'N/A',
            'nivel' => $turma->nivel ? $turma->nivel->nome : 'N/A',
            'professor' => $turma->professor?->usuario ? $turma->professor->usuario->nome : 'Não atribuído',
            'status' => $turma->status,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'horarios' => $turma->horarios->map(fn ($h) => [
                'dia_semana' => $h->dia_semana,
                'hora_inicio' => $h->hora_inicio,
                'hora_termino' => $h->hora_termino,
            ]),
            'aulas' => $aulas->map(fn ($aula) => [
                'id' => $aula->id,
                'data' => $aula->data?->format('Y-m-d'),
                'hora_inicio' => $aula->hora_inicio,
                'conteudo_dado' => $aula->conteudo_dado,
                'presencas' => $aula->presencas->mapWithKeys(fn ($p) => [
                    $p->id_aluno => self::STATUS_PRESENCA[$p->status] ?? $p->status,
                ]),
            ]),
            'alunos' => $turma->matriculas->map(fn ($m) => [
                'id_aluno' => $m->id_aluno,
                'nome' => $m->aluno?->usuario ? $m->aluno->usuario->nome : 'N/A',
                'data_matricula' => $m->data,
                'status' => $m->status,
            ])->sortBy('nome')->values(),
        ];
    }
}

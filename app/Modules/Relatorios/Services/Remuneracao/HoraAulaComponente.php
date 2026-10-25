<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Pessoas\Models\Professor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hora-aula pela duração das aulas concluídas no mês (1h30 = 1,5 × valor da hora).
 * Usa o valor congelado na conclusão da aula; sem ele, turma sobrepõe professor.
 */
class HoraAulaComponente implements ComponenteRemuneracao
{
    public function chave(): string
    {
        return 'aulas';
    }

    public function calcular(Professor $professor, Competencia $competencia): array
    {
        $aulas = AulaTurma::query()
            ->where('status', 'concluida')
            ->whereBetween('data', [$competencia->inicio->toDateString(), $competencia->fim->toDateString()])
            ->where(fn (Builder $q) => $q
                ->where('id_professor', $professor->id)
                ->orWhere(fn (Builder $semProfessor) => $semProfessor
                    ->whereNull('id_professor')
                    ->whereHas('turma', fn (Builder $t) => $t->where('id_professor', $professor->id))))
            ->with(['turma.curso', 'turma.nivel', 'curso', 'aluno_especifico.usuario'])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();

        $itens = $aulas->map(function (AulaTurma $aula) use ($professor): array {
            $horas = $this->horas($aula->hora_inicio, $aula->hora_termino);
            $valorHora = round((float) ($aula->valor_hora_aula_aplicado
                ?? $aula->turma?->valor_hora_aula_especifico
                ?? $professor->valor_hora_aula), 2);

            return [
                'id' => $aula->id,
                'data' => $aula->data->format('Y-m-d'),
                'hora_inicio' => substr((string) $aula->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $aula->hora_termino, 0, 5),
                'tipo' => $aula->tipo,
                'horas' => $horas,
                'valor_hora' => $valorHora,
                'valor' => round($horas * $valorHora, 2),
                'turma' => TurmaResumida::de($aula->turma),
                'curso' => $aula->turma ? null : ($aula->curso ? ['nome' => $aula->curso->nome] : null),
                'aluno' => $aula->aluno_especifico ? [
                    'id' => $aula->aluno_especifico->id,
                    'nome' => $aula->aluno_especifico->usuario?->nome,
                ] : null,
            ];
        })->all();

        $total = round(array_sum(array_column($itens, 'valor')), 2);

        return [
            'total' => $total,
            'itens' => $itens,
            'resumo' => [
                'total_aulas' => count($itens),
                'total_horas' => round(array_sum(array_column($itens, 'horas')), 2),
                'valor_hora_aula' => $total,
            ],
        ];
    }

    private function horas(?string $inicio, ?string $termino): float
    {
        if (! $inicio || ! $termino) {
            return 0.0;
        }

        $minutos = Carbon::parse($inicio)->diffInMinutes(Carbon::parse($termino), false);

        return $minutos > 0 ? round($minutos / 60, 2) : 0.0;
    }
}

<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use App\Modules\Academico\Models\Turma;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comissão sobre o que foi efetivamente pago no mês (data do pagamento) pelos alunos
 * das turmas e aulas individuais do professor. Percentual: aula congelada > turma > professor.
 */
class ComissaoComponente implements ComponenteRemuneracao
{
    private const INDIVIDUAIS = 'individuais';

    public function chave(): string
    {
        return 'comissoes';
    }

    public function calcular(Professor $professor, Competencia $competencia): array
    {
        $pagamentos = ContaPagamento::query()
            ->whereBetween('data_pagamento', [$competencia->inicio->toDateString(), $competencia->fim->toDateString()])
            ->whereHas('conta', fn (Builder $conta) => $this->doProfessor($conta, $professor->id))
            ->with([
                'conta.matricula.turma.curso',
                'conta.matricula.turma.nivel',
                'conta.aula_turma.turma.curso',
                'conta.aula_turma.turma.nivel',
            ])
            ->get();

        $grupos = [];

        foreach ($pagamentos as $pagamento) {
            [$turma, $percentual] = $this->origem($pagamento, $professor);
            $chave = $turma ? 'turma:'.$turma->id : self::INDIVIDUAIS;
            $valorPago = (float) $pagamento->valor_pago;

            $grupos[$chave] ??= [
                'turma' => TurmaResumida::de($turma),
                'base' => 0.0,
                'valor' => 0.0,
                'pagamentos' => 0,
                'alunos' => [],
                'percentuais' => [],
            ];

            $grupos[$chave]['base'] += $valorPago;
            $grupos[$chave]['valor'] += $valorPago * $percentual / 100;
            $grupos[$chave]['pagamentos']++;
            $grupos[$chave]['alunos'][$pagamento->conta->id_aluno] = true;
            $grupos[$chave]['percentuais'][(string) round($percentual, 2)] = true;
        }

        $itens = collect($grupos)
            ->map(function (array $grupo, string $chave): array {
                $percentuais = array_keys($grupo['percentuais']);

                return [
                    'chave' => $chave,
                    'turma' => $grupo['turma'],
                    'base' => round($grupo['base'], 2),
                    'percentual' => count($percentuais) === 1 ? (float) $percentuais[0] : null,
                    'valor' => round($grupo['valor'], 2),
                    'pagamentos' => $grupo['pagamentos'],
                    'alunos' => count($grupo['alunos']),
                ];
            })
            ->sortBy(fn (array $item) => $item['turma'] === null
                ? "\u{FFFF}"
                : mb_strtolower(($item['turma']['curso']['nome'] ?? '').' '.($item['turma']['nivel']['nome'] ?? '').' '.($item['turma']['descricao'] ?? '')))
            ->values()
            ->all();

        $total = round(array_sum(array_column($itens, 'valor')), 2);

        return [
            'total' => $total,
            'itens' => $itens,
            'resumo' => [
                'base_comissao' => round(array_sum(array_column($itens, 'base')), 2),
                'valor_comissao' => $total,
            ],
        ];
    }

    /** Receitas de mensalidade (matrícula) ou de aula avulsa ligadas ao professor. */
    private function doProfessor(Builder $conta, int $professorId): Builder
    {
        $daTurma = fn (Builder $t) => $t->where('id_professor', $professorId);

        return $conta->where('tipo', 'receita')->where(fn (Builder $q) => $q
            ->whereHas('matricula', fn (Builder $m) => $m->where(fn (Builder $mm) => $mm
                ->whereHas('turma', $daTurma)
                ->orWhere(fn (Builder $individual) => $individual
                    ->whereNull('id_turma')
                    ->where('id_professor', $professorId))))
            ->orWhereHas('aula_turma', fn (Builder $a) => $a->where(fn (Builder $aa) => $aa
                ->where('id_professor', $professorId)
                ->orWhere(fn (Builder $semProfessor) => $semProfessor
                    ->whereNull('id_professor')
                    ->whereHas('turma', $daTurma)))));
    }

    /** @return array{0: ?Turma, 1: float} */
    private function origem(ContaPagamento $pagamento, Professor $professor): array
    {
        $conta = $pagamento->conta;

        if ($conta->matricula) {
            $turma = $conta->matricula->id_turma ? $conta->matricula->turma : null;

            return [$turma, (float) ($turma?->percentual_comissao_especifico ?? $professor->comissao)];
        }

        $aula = $conta->aula_turma;

        return [$aula?->turma, (float) ($aula?->percentual_comissao_aplicado
            ?? $aula?->turma?->percentual_comissao_especifico
            ?? $professor->comissao)];
    }
}

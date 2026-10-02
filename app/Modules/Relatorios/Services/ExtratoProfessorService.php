<?php

namespace App\Modules\Relatorios\Services;

use App\Modules\Pessoas\Models\Professor;
use App\Modules\Relatorios\Models\FechamentoProfessor;
use App\Modules\Relatorios\Services\Remuneracao\ComissaoComponente;
use App\Modules\Relatorios\Services\Remuneracao\Competencia;
use App\Modules\Relatorios\Services\Remuneracao\ComponenteRemuneracao;
use App\Modules\Relatorios\Services\Remuneracao\HoraAulaComponente;
use App\Modules\Relatorios\Services\Remuneracao\SalarioFixoComponente;

/**
 * Extrato mensal do professor: soma salário fixo, hora-aula e comissão.
 * Meses fechados devolvem o cálculo congelado no fechamento.
 */
class ExtratoProfessorService
{
    public const SITUACAO_EM_ANDAMENTO = 'em_andamento';

    public const SITUACAO_ABERTO = 'aberto';

    public const SITUACAO_FECHADO = 'fechado';

    /** @var ComponenteRemuneracao[] */
    private array $componentes;

    public function __construct(
        SalarioFixoComponente $salarioFixo,
        HoraAulaComponente $horaAula,
        ComissaoComponente $comissao,
    ) {
        $this->componentes = [$salarioFixo, $horaAula, $comissao];
    }

    /**
     * Cálculo ao vivo (ignora fechamentos).
     *
     * @return array{regras: array<string, float>, resumo: array<string, float|int>, aulas: array, comissoes: array}
     */
    public function calcular(Professor $professor, Competencia $competencia): array
    {
        $calculo = [
            'regras' => [
                'salario_fixo' => round((float) $professor->salario_fixo, 2),
                'valor_hora_aula' => round((float) $professor->valor_hora_aula, 2),
                'comissao' => round((float) $professor->comissao, 2),
            ],
            'resumo' => [],
            'aulas' => [],
            'comissoes' => [],
        ];
        $total = 0.0;

        foreach ($this->componentes as $componente) {
            $resultado = $componente->calcular($professor, $competencia);
            $calculo['resumo'] += $resultado['resumo'];
            $total += $resultado['total'];

            if (array_key_exists($componente->chave(), $calculo)) {
                $calculo[$componente->chave()] = $resultado['itens'];
            }
        }

        $calculo['resumo']['valor_total'] = round($total, 2);

        return $calculo;
    }

    public function fechamento(Professor $professor, Competencia $competencia): ?FechamentoProfessor
    {
        return FechamentoProfessor::query()
            ->where('id_professor', $professor->id)
            ->where('ano', $competencia->ano)
            ->where('mes', $competencia->mes)
            ->with(['conta', 'usuario'])
            ->latest('id')
            ->first();
    }

    public function montar(Professor $professor, Competencia $competencia): array
    {
        $professor->loadMissing('usuario');
        $fechamento = $this->fechamento($professor, $competencia);
        $calculo = $fechamento?->detalhes ?? $this->calcular($professor, $competencia);

        return [
            'competencia' => $competencia->rotulo(),
            'periodo' => [
                'inicio' => $competencia->inicio->toDateString(),
                'fim' => $competencia->fim->toDateString(),
            ],
            'situacao' => $this->situacao($competencia, $fechamento),
            'professor' => ['id' => $professor->id, 'nome' => $professor->usuario?->nome],
            ...$calculo,
            'fechamento' => $fechamento ? $this->fechamentoResumido($fechamento) : null,
        ];
    }

    /** Visão do mês para todos os professores (resumo sem as linhas de aulas/comissões). */
    public function visaoGeral(Competencia $competencia): array
    {
        return Professor::query()
            ->with('usuario')
            ->get()
            ->map(function (Professor $professor) use ($competencia): array {
                $extrato = $this->montar($professor, $competencia);

                return [
                    'professor' => $extrato['professor'],
                    'situacao' => $extrato['situacao'],
                    'resumo' => $extrato['resumo'],
                    'fechamento' => $extrato['fechamento'],
                ];
            })
            ->sortBy(fn (array $linha) => mb_strtolower((string) $linha['professor']['nome']))
            ->values()
            ->all();
    }

    private function situacao(Competencia $competencia, ?FechamentoProfessor $fechamento): string
    {
        if ($fechamento) {
            return self::SITUACAO_FECHADO;
        }

        return $competencia->emAndamento() ? self::SITUACAO_EM_ANDAMENTO : self::SITUACAO_ABERTO;
    }

    private function fechamentoResumido(FechamentoProfessor $fechamento): array
    {
        $conta = $fechamento->conta;

        return [
            'id' => $fechamento->id,
            'fechado_em' => $fechamento->fechado_em?->toIso8601String(),
            'fechado_por' => $fechamento->usuario?->nome,
            'conta' => $conta ? [
                'id' => $conta->id,
                'status' => $conta->trashed() ? 'cancelado' : $conta->status,
                'valor' => (float) $conta->valor,
                'data_vencimento' => $conta->data_vencimento ? substr((string) $conta->data_vencimento, 0, 10) : null,
                'data_pagamento' => $conta->data_pagamento ? substr((string) $conta->data_pagamento, 0, 10) : null,
            ] : null,
        ];
    }
}

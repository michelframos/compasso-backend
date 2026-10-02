<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use App\Models\Lead;
use App\Models\Matricula;

class DashboardResumoService
{
    public function build(DashboardPeriod $period): array
    {
        $alunosMatriculados = $this->countAlunosAtivos();
        $alunosAnterior = $this->countAlunosAtivosAte($period->previousEnd);

        $faturamentoMensal = DashboardContaQuery::sumFaturamento($period->start, $period->end);
        $faturamentoAnterior = DashboardContaQuery::sumFaturamento($period->previousStart, $period->previousEnd);

        $inadimplencia = DashboardContaQuery::sumSaldoAtrasado($period->today);

        $ticketMedio = $alunosMatriculados > 0 ? $faturamentoMensal / $alunosMatriculados : 0;
        $ticketAnterior = $alunosAnterior > 0 ? $faturamentoAnterior / $alunosAnterior : 0;

        $leadsAtivos = Lead::whereNotIn('status', ['matriculado', 'perdido'])->count();

        $trendAlunos = $this->trendPercent($alunosMatriculados, $alunosAnterior);
        $trendFaturamento = $this->trendPercent($faturamentoMensal, $faturamentoAnterior);
        $trendTicket = $this->trendPercent($ticketMedio, $ticketAnterior);

        return [
            'mes' => $period->monthKey(),
            'leadsAtivos' => $leadsAtivos,
            'stats' => [
                [
                    'title' => 'Alunos Matriculados',
                    'value' => (string) $alunosMatriculados,
                    'icon' => 'Users',
                    'href' => '/alunos',
                    'trend' => $this->trendPayload($trendAlunos),
                ],
                [
                    'title' => 'Faturamento Mensal',
                    'value' => $this->formatMoney($faturamentoMensal, 0),
                    'icon' => 'DollarSign',
                    'href' => '/financeiro',
                    'trend' => $this->trendPayload($trendFaturamento),
                ],
                [
                    'title' => 'Inadimplência',
                    'value' => $this->formatMoney($inadimplencia, 0),
                    'icon' => 'AlertCircle',
                    'href' => '/relatorios/financeiro/inadimplencia',
                    'trend' => ['value' => 0, 'isUp' => false],
                    'variant' => 'danger',
                ],
                [
                    'title' => 'Ticket Médio',
                    'value' => $this->formatMoney($ticketMedio, 2),
                    'icon' => 'TrendingUp',
                    'href' => '/financeiro',
                    'trend' => $this->trendPayload($trendTicket),
                ],
                [
                    'title' => 'Leads Ativos',
                    'value' => (string) $leadsAtivos,
                    'icon' => 'UserPlus',
                    'href' => '/leads',
                    'trend' => ['value' => 0, 'isUp' => true],
                ],
            ],
        ];
    }

    private function countAlunosAtivos(): int
    {
        return (int) Matricula::query()
            ->whereNotIn('status', ['cancelada', 'transferida'])
            ->distinct()
            ->count('id_aluno');
    }

    private function countAlunosAtivosAte($dataLimite): int
    {
        return (int) Matricula::query()
            ->whereNotIn('status', ['cancelada', 'transferida'])
            ->whereDate('data', '<=', $dataLimite->toDateString())
            ->distinct()
            ->count('id_aluno');
    }

    private function trendPercent(float $atual, float $anterior): float
    {
        if ($anterior <= 0) {
            return $atual > 0 ? 100.0 : 0.0;
        }

        return (($atual - $anterior) / $anterior) * 100;
    }

    private function trendPayload(float $trend): array
    {
        return [
            'value' => round(abs($trend), 1),
            'isUp' => $trend >= 0,
        ];
    }

    private function formatMoney(float $value, int $decimals): string
    {
        return 'R$ ' . number_format($value, $decimals, ',', '.');
    }
}

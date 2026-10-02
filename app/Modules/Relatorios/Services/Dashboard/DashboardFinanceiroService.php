<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use App\Models\Aluno;
use App\Models\ContaPagamento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardFinanceiroService
{
    public function build(DashboardPeriod $period): array
    {
        return [
            'mes' => $period->monthKey(),
            'revenueData' => $this->revenueData($period),
            'fluxoRecebimentoData' => $this->fluxoRecebimento(),
            'inadimplenciaData' => $this->inadimplenciaData($period),
            'inadimplenciaItens' => $this->inadimplenciaItens($period),
            'recentPayments' => $this->recentPayments(),
        ];
    }

    private function revenueData(DashboardPeriod $period): array
    {
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $mes = $period->start->copy()->subMonthsNoOverflow($i);
            $inicio = $mes->copy()->startOfMonth();
            $fim = $mes->copy()->endOfMonth();

            $data[] = [
                'name' => ucfirst($mes->locale('pt_BR')->translatedFormat('M')),
                'value' => DashboardContaQuery::sumFaturamento($inicio, $fim),
            ];
        }

        return $data;
    }

    private function fluxoRecebimento(): array
    {
        $hoje = Carbon::today();
        $data = [];

        for ($i = 0; $i < 3; $i++) {
            $mes = $hoje->copy()->addMonthsNoOverflow($i);
            $inicio = $i === 0 ? $hoje->copy() : $mes->copy()->startOfMonth();
            $fim = $mes->copy()->endOfMonth();

            $data[] = [
                'name' => ucfirst($mes->locale('pt_BR')->translatedFormat('M')),
                'value' => DashboardContaQuery::sumSaldoAVencer($inicio, $fim, $hoje),
            ];
        }

        return $data;
    }

    private function inadimplenciaData(DashboardPeriod $period): array
    {
        $pago = DashboardContaQuery::sumFaturamento($period->start, $period->end);
        $aVencer = DashboardContaQuery::sumSaldoAVencer($period->start, $period->end, $period->today);
        $atrasado = DashboardContaQuery::sumSaldoAtrasado($period->today, $period->start, $period->end);

        return [
            ['name' => 'Pago', 'value' => $pago],
            ['name' => 'A vencer', 'value' => $aVencer],
            ['name' => 'Atrasado', 'value' => $atrasado],
        ];
    }

    private function inadimplenciaItens(DashboardPeriod $period): array
    {
        $hoje = $period->today;

        $rows = DashboardContaQuery::openReceitaBase()
            ->where('contas.data_vencimento', '<', $hoje->toDateString())
            ->orderBy('contas.data_vencimento')
            ->limit(10)
            ->get([
                'contas.id',
                'contas.descricao',
                'contas.data_vencimento',
                'contas.id_aluno',
                DB::raw(DashboardContaQuery::saldoExpr() . ' as saldo_devedor'),
            ]);

        $alunos = Aluno::with('usuario')
            ->whereIn('id', $rows->pluck('id_aluno')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $rows->map(function ($conta) use ($hoje, $alunos) {
            $nome = $alunos->get($conta->id_aluno)?->usuario?->nome
                ?? ($conta->descricao ?: 'Sem vínculo');

            return [
                'id' => (int) $conta->id,
                'name' => $nome,
                'description' => $conta->descricao,
                'dueDate' => Carbon::parse($conta->data_vencimento)->locale('pt_BR')->translatedFormat('d M Y'),
                'daysOverdue' => (int) Carbon::parse($conta->data_vencimento)->startOfDay()->diffInDays($hoje),
                'amount' => 'R$ ' . number_format((float) $conta->saldo_devedor, 2, ',', '.'),
            ];
        })->values()->all();
    }

    private function recentPayments(): array
    {
        return ContaPagamento::with(['conta.matricula.aluno.usuario', 'conta.aluno.usuario'])
            ->orderByDesc('data_pagamento')
            ->orderByDesc('id')
            ->take(5)
            ->get()
            ->map(function (ContaPagamento $pagamento) {
                $nome = $pagamento->conta?->matricula?->aluno?->usuario?->nome
                    ?? $pagamento->conta?->aluno?->usuario?->nome
                    ?? $pagamento->conta?->descricao
                    ?? 'Receita Genérica';

                return [
                    'id' => $pagamento->id,
                    'name' => $nome,
                    'date' => Carbon::parse($pagamento->data_pagamento)->locale('pt_BR')->translatedFormat('d M Y'),
                    'amount' => 'R$ ' . number_format((float) $pagamento->valor_pago, 2, ',', '.'),
                    'status' => 'Pago',
                ];
            })
            ->values()
            ->all();
    }
}

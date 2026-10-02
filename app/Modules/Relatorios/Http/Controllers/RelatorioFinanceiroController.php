<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conta;
use App\Modules\Relatorios\Services\RelatorioFluxoCaixaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioFinanceiroController extends Controller
{
    public function __construct(
        private readonly RelatorioFluxoCaixaService $fluxoCaixaService,
    ) {}

    /**
     * Comparativo mensal previsto vs realizado, com saldo acumulado.
     */
    public function fluxoCaixa(Request $request)
    {
        $tipo = $request->input('tipo');
        $idCategoria = $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null;

        return response()->json(
            $this->fluxoCaixaService->resumir(
                $request->input('data_inicio'),
                $request->input('data_fim'),
                in_array($tipo, ['receita', 'despesa'], true) ? $tipo : null,
                $idCategoria
            )
        );
    }

    /**
     * Movimentos de um mês (contas previstas ou pagamentos realizados).
     */
    public function fluxoCaixaMovimentos(Request $request)
    {
        $periodo = (string) $request->input('periodo', '');
        if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return response()->json(['message' => 'Informe o período no formato YYYY-MM.'], 422);
        }

        $origem = $request->input('origem', 'realizado') === 'previsto' ? 'previsto' : 'realizado';
        $tipo = $request->input('tipo');
        $idCategoria = $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null;

        return response()->json([
            'periodo' => $periodo,
            'origem' => $origem,
            'data' => $this->fluxoCaixaService->movimentos(
                $periodo,
                $origem,
                $request->input('lado'),
                in_array($tipo, ['receita', 'despesa'], true) ? $tipo : null,
                $idCategoria
            ),
        ]);
    }

    /**
     * Retorna os dados para o Relatório de Inadimplência
     */
    public function inadimplencia(Request $request)
    {
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        $query = Conta::with(['aluno.usuario', 'aluno.responsaveis.usuario'])
            ->where('tipo', 'receita')
            ->whereIn('status', ['pendente', 'vencido', 'pago_parcialmente'])
            ->whereNotNull('id_aluno');

        if ($dataInicio) {
            $query->where('data_vencimento', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_vencimento', '<=', $dataFim);
        }

        $contas = $query->orderBy('data_vencimento', 'asc')->get();

        // Calcular sumário
        $totalAtraso = 0;
        foreach ($contas as $conta) {
            $pago = DB::table('conta_pagamentos')
                ->where('id_conta', $conta->id)
                ->whereNull('deleted_at')
                ->sum('valor_pago');
            
            $totalAtraso += max(0, $conta->valor - $pago);
        }

        return response()->json([
            'data' => $contas,
            'summary' => [
                'total_atraso' => (float) $totalAtraso,
                'quantidade_vencidos' => $contas->count(),
            ],
            'filters' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]
        ]);
    }
}

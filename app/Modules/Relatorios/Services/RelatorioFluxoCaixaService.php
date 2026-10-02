<?php

namespace App\Modules\Relatorios\Services;

use App\Modules\Core\Support\InstituicaoContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RelatorioFluxoCaixaService
{
    public function resumir(?string $dataInicio, ?string $dataFim, ?string $tipo, ?int $idCategoria): array
    {
        $inicio = $this->parseDate($dataInicio, Carbon::now()->subMonths(6)->startOfMonth());
        $fim = $this->parseDate($dataFim, Carbon::now()->addMonths(3)->endOfMonth());

        if ($inicio->gt($fim)) {
            [$inicio, $fim] = [$fim->copy()->startOfMonth(), $inicio->copy()->endOfMonth()];
        } else {
            $inicio = $inicio->copy()->startOfDay();
            $fim = $fim->copy()->endOfDay();
        }

        $meses = $this->mesesEntre($inicio, $fim);
        $previsto = $this->previstoPorMes($inicio, $fim, $tipo, $idCategoria);
        $realizado = $this->realizadoPorMes($inicio, $fim, $tipo, $idCategoria);
        $categorias = $this->categoriasPorMes($inicio, $fim, $tipo, $idCategoria);

        $acumuladoRealizado = 0.0;
        $acumuladoPrevisto = 0.0;

        $data = [];
        foreach ($meses as $periodo) {
            $p = $previsto->get($periodo);
            $r = $realizado->get($periodo);

            $previstoEntrada = (float) ($p->previsto_entrada ?? 0);
            $previstoSaida = (float) ($p->previsto_saida ?? 0);
            $emAbertoEntrada = max(0, (float) ($p->em_aberto_entrada ?? 0));
            $emAbertoSaida = max(0, (float) ($p->em_aberto_saida ?? 0));
            $pagoCompetenciaEntrada = (float) ($p->pago_competencia_entrada ?? 0);
            $pagoCompetenciaSaida = (float) ($p->pago_competencia_saida ?? 0);
            $realizadoEntrada = (float) ($r->entrada ?? 0);
            $realizadoSaida = (float) ($r->saida ?? 0);

            $saldoPrevisto = $previstoEntrada - $previstoSaida;
            $saldoRealizado = $realizadoEntrada - $realizadoSaida;
            $acumuladoPrevisto += $saldoPrevisto;
            $acumuladoRealizado += $saldoRealizado;

            $data[] = [
                'periodo' => $periodo,
                'previsto_entrada' => round($previstoEntrada, 2),
                'previsto_saida' => round($previstoSaida, 2),
                'em_aberto_entrada' => round($emAbertoEntrada, 2),
                'em_aberto_saida' => round($emAbertoSaida, 2),
                'pago_competencia_entrada' => round($pagoCompetenciaEntrada, 2),
                'pago_competencia_saida' => round($pagoCompetenciaSaida, 2),
                'realizado_entrada' => round($realizadoEntrada, 2),
                'realizado_saida' => round($realizadoSaida, 2),
                'saldo_previsto' => round($saldoPrevisto, 2),
                'saldo_realizado' => round($saldoRealizado, 2),
                'variacao_entrada' => round($realizadoEntrada - $previstoEntrada, 2),
                'variacao_saida' => round($realizadoSaida - $previstoSaida, 2),
                'saldo_previsto_acumulado' => round($acumuladoPrevisto, 2),
                'saldo_realizado_acumulado' => round($acumuladoRealizado, 2),
                'categorias' => $categorias->get($periodo, []),
            ];
        }

        $sum = fn (string $key) => round(array_sum(array_column($data, $key)), 2);
        $previstoEntradaTotal = $sum('previsto_entrada');
        $pagoCompetenciaEntrada = $sum('pago_competencia_entrada');

        return [
            'data' => $data,
            'summary' => [
                'previsto_entrada' => $previstoEntradaTotal,
                'previsto_saida' => $sum('previsto_saida'),
                'realizado_entrada' => $sum('realizado_entrada'),
                'realizado_saida' => $sum('realizado_saida'),
                'em_aberto_entrada' => $sum('em_aberto_entrada'),
                'em_aberto_saida' => $sum('em_aberto_saida'),
                'saldo_previsto' => $sum('saldo_previsto'),
                'saldo_realizado' => $sum('saldo_realizado'),
                'saldo_realizado_acumulado' => round($acumuladoRealizado, 2),
                'eficiencia_entrada' => $previstoEntradaTotal > 0
                    ? round(($pagoCompetenciaEntrada / $previstoEntradaTotal) * 100, 1)
                    : 0,
            ],
            'filters' => [
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'tipo' => $tipo,
                'id_categoria' => $idCategoria,
            ],
        ];
    }

    public function movimentos(string $periodo, string $origem, ?string $lado, ?string $tipo, ?int $idCategoria): array
    {
        $mes = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
        $inicio = $mes->copy()->startOfMonth()->toDateString();
        $fim = $mes->copy()->endOfMonth()->toDateString();
        $lado = in_array($lado, ['receita', 'despesa'], true) ? $lado : null;
        $tipoFiltro = in_array($tipo, ['receita', 'despesa'], true) ? $tipo : $lado;

        if ($origem === 'previsto') {
            return $this->movimentosPrevistos($inicio, $fim, $tipoFiltro, $idCategoria);
        }

        return $this->movimentosRealizados($inicio, $fim, $tipoFiltro, $idCategoria);
    }

    private function previstoPorMes(Carbon $inicio, Carbon $fim, ?string $tipo, ?int $idCategoria)
    {
        $pagosSub = $this->pagosSubquery();

        $query = DB::table('contas')
            ->leftJoinSub($pagosSub, 'pagos', 'pagos.id_conta', '=', 'contas.id')
            ->whereNull('contas.deleted_at')
            ->where('contas.status', '!=', 'cancelado')
            ->whereBetween('contas.data_vencimento', [$inicio->toDateString(), $fim->toDateString()]);

        $this->aplicarFiltrosConta($query, $tipo, $idCategoria);

        return $query
            ->select(
                DB::raw("DATE_FORMAT(contas.data_vencimento, '%Y-%m') as periodo"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'receita' THEN contas.valor ELSE 0 END) as previsto_entrada"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'despesa' THEN contas.valor ELSE 0 END) as previsto_saida"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'receita' THEN GREATEST(0, contas.valor - COALESCE(pagos.total_pago, 0)) ELSE 0 END) as em_aberto_entrada"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'despesa' THEN GREATEST(0, contas.valor - COALESCE(pagos.total_pago, 0)) ELSE 0 END) as em_aberto_saida"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'receita' THEN COALESCE(pagos.total_pago, 0) ELSE 0 END) as pago_competencia_entrada"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'despesa' THEN COALESCE(pagos.total_pago, 0) ELSE 0 END) as pago_competencia_saida")
            )
            ->groupBy('periodo')
            ->get()
            ->keyBy('periodo');
    }

    private function realizadoPorMes(Carbon $inicio, Carbon $fim, ?string $tipo, ?int $idCategoria)
    {
        $query = DB::table('conta_pagamentos')
            ->join('contas', 'conta_pagamentos.id_conta', '=', 'contas.id')
            ->whereNull('conta_pagamentos.deleted_at')
            ->whereNull('contas.deleted_at')
            ->where('contas.status', '!=', 'cancelado')
            ->whereBetween('conta_pagamentos.data_pagamento', [$inicio->toDateString(), $fim->toDateString()]);

        InstituicaoContext::applyToQuery($query, 'conta_pagamentos');
        $this->aplicarFiltrosConta($query, $tipo, $idCategoria);

        return $query
            ->select(
                DB::raw("DATE_FORMAT(conta_pagamentos.data_pagamento, '%Y-%m') as periodo"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'receita' THEN conta_pagamentos.valor_pago ELSE 0 END) as entrada"),
                DB::raw("SUM(CASE WHEN contas.tipo = 'despesa' THEN conta_pagamentos.valor_pago ELSE 0 END) as saida")
            )
            ->groupBy('periodo')
            ->get()
            ->keyBy('periodo');
    }

    private function categoriasPorMes(Carbon $inicio, Carbon $fim, ?string $tipo, ?int $idCategoria)
    {
        $query = DB::table('conta_pagamentos')
            ->join('contas', 'conta_pagamentos.id_conta', '=', 'contas.id')
            ->leftJoin('categorias_contas', 'contas.id_categoria', '=', 'categorias_contas.id')
            ->whereNull('conta_pagamentos.deleted_at')
            ->whereNull('contas.deleted_at')
            ->where('contas.status', '!=', 'cancelado')
            ->whereBetween('conta_pagamentos.data_pagamento', [$inicio->toDateString(), $fim->toDateString()]);

        InstituicaoContext::applyToQuery($query, 'conta_pagamentos');
        $this->aplicarFiltrosConta($query, $tipo, $idCategoria);

        return $query
            ->select(
                DB::raw("DATE_FORMAT(conta_pagamentos.data_pagamento, '%Y-%m') as periodo"),
                DB::raw("COALESCE(categorias_contas.nome, 'Sem categoria') as categoria"),
                'contas.tipo',
                DB::raw('SUM(conta_pagamentos.valor_pago) as valor')
            )
            ->groupBy('periodo', 'categoria', 'contas.tipo')
            ->orderByDesc('valor')
            ->get()
            ->groupBy('periodo')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'categoria' => $row->categoria,
                'tipo' => $row->tipo,
                'valor' => round((float) $row->valor, 2),
            ])->values()->all());
    }

    private function movimentosPrevistos(string $inicio, string $fim, ?string $tipo, ?int $idCategoria): array
    {
        $pagosSub = $this->pagosSubquery();

        $query = DB::table('contas')
            ->leftJoinSub($pagosSub, 'pagos', 'pagos.id_conta', '=', 'contas.id')
            ->leftJoin('alunos', 'contas.id_aluno', '=', 'alunos.id')
            ->leftJoin('usuarios as usuarios_alunos', 'alunos.id_usuario', '=', 'usuarios_alunos.id')
            ->leftJoin('professores', 'contas.id_professor', '=', 'professores.id')
            ->leftJoin('usuarios as usuarios_professores', 'professores.id_usuario', '=', 'usuarios_professores.id')
            ->leftJoin('categorias_contas', 'contas.id_categoria', '=', 'categorias_contas.id')
            ->whereNull('contas.deleted_at')
            ->where('contas.status', '!=', 'cancelado')
            ->whereBetween('contas.data_vencimento', [$inicio, $fim]);

        $this->aplicarFiltrosConta($query, $tipo, $idCategoria);

        return $query
            ->orderBy('contas.data_vencimento')
            ->limit(100)
            ->get([
                'contas.id',
                'contas.descricao',
                'contas.tipo',
                'contas.status',
                'contas.valor',
                'contas.data_vencimento',
                DB::raw('GREATEST(0, contas.valor - COALESCE(pagos.total_pago, 0)) as saldo_devedor'),
                DB::raw('COALESCE(usuarios_alunos.nome, usuarios_professores.nome) as titular'),
                DB::raw("COALESCE(categorias_contas.nome, 'Sem categoria') as categoria"),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'descricao' => $row->descricao,
                'tipo' => $row->tipo,
                'status' => $row->status,
                'titular' => $row->titular,
                'categoria' => $row->categoria,
                'data' => $row->data_vencimento,
                'valor' => (float) $row->valor,
                'saldo_devedor' => (float) $row->saldo_devedor,
            ])
            ->all();
    }

    private function movimentosRealizados(string $inicio, string $fim, ?string $tipo, ?int $idCategoria): array
    {
        $query = DB::table('conta_pagamentos')
            ->join('contas', 'conta_pagamentos.id_conta', '=', 'contas.id')
            ->leftJoin('alunos', 'contas.id_aluno', '=', 'alunos.id')
            ->leftJoin('usuarios as usuarios_alunos', 'alunos.id_usuario', '=', 'usuarios_alunos.id')
            ->leftJoin('professores', 'contas.id_professor', '=', 'professores.id')
            ->leftJoin('usuarios as usuarios_professores', 'professores.id_usuario', '=', 'usuarios_professores.id')
            ->leftJoin('categorias_contas', 'contas.id_categoria', '=', 'categorias_contas.id')
            ->whereNull('conta_pagamentos.deleted_at')
            ->whereNull('contas.deleted_at')
            ->where('contas.status', '!=', 'cancelado')
            ->whereBetween('conta_pagamentos.data_pagamento', [$inicio, $fim]);

        $this->aplicarFiltrosConta($query, $tipo, $idCategoria);

        return $query
            ->orderByDesc('conta_pagamentos.data_pagamento')
            ->limit(100)
            ->get([
                'conta_pagamentos.id',
                'contas.id as id_conta',
                'contas.descricao',
                'contas.tipo',
                'conta_pagamentos.valor_pago',
                'conta_pagamentos.data_pagamento',
                'conta_pagamentos.forma_pagamento',
                DB::raw('COALESCE(usuarios_alunos.nome, usuarios_professores.nome) as titular'),
                DB::raw("COALESCE(categorias_contas.nome, 'Sem categoria') as categoria"),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'id_conta' => (int) $row->id_conta,
                'descricao' => $row->descricao,
                'tipo' => $row->tipo,
                'titular' => $row->titular,
                'categoria' => $row->categoria,
                'data' => $row->data_pagamento,
                'valor' => (float) $row->valor_pago,
                'forma_pagamento' => $row->forma_pagamento,
            ])
            ->all();
    }

    private function aplicarFiltrosConta($query, ?string $tipo, ?int $idCategoria): void
    {
        InstituicaoContext::applyToQuery($query, 'contas');

        if (in_array($tipo, ['receita', 'despesa'], true)) {
            $query->where('contas.tipo', $tipo);
        }
        if ($idCategoria) {
            $query->where('contas.id_categoria', $idCategoria);
        }
    }

    private function pagosSubquery()
    {
        $query = DB::table('conta_pagamentos')
            ->select('id_conta', DB::raw('COALESCE(SUM(valor_pago), 0) as total_pago'))
            ->whereNull('deleted_at')
            ->groupBy('id_conta');

        InstituicaoContext::applyToQuery($query, 'conta_pagamentos');

        return $query;
    }

    private function mesesEntre(Carbon $inicio, Carbon $fim): array
    {
        $meses = [];
        $cursor = $inicio->copy()->startOfMonth();
        $ultimo = $fim->copy()->startOfMonth();

        while ($cursor->lte($ultimo)) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $meses;
    }

    private function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}

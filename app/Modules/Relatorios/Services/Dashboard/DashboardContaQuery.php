<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use App\Modules\Core\Support\InstituicaoContext;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardContaQuery
{
    public const OPEN_STATUSES = ['pendente', 'vencido', 'pago_parcialmente'];

    public static function pagosSubquery(): Builder
    {
        $query = DB::table('conta_pagamentos')
            ->select('id_conta', DB::raw('COALESCE(SUM(valor_pago), 0) as total_pago'))
            ->whereNull('deleted_at')
            ->groupBy('id_conta');

        InstituicaoContext::applyToQuery($query, 'conta_pagamentos');

        return $query;
    }

    public static function openReceitaBase(): Builder
    {
        $query = DB::table('contas')
            ->leftJoinSub(self::pagosSubquery(), 'pagos', 'pagos.id_conta', '=', 'contas.id')
            ->where('contas.tipo', 'receita')
            ->whereIn('contas.status', self::OPEN_STATUSES)
            ->whereNull('contas.deleted_at')
            ->whereRaw('(contas.valor - COALESCE(pagos.total_pago, 0)) > 0');

        InstituicaoContext::applyToQuery($query, 'contas');

        return $query;
    }

    public static function saldoExpr(): string
    {
        return '(contas.valor - COALESCE(pagos.total_pago, 0))';
    }

    public static function sumSaldoAtrasado(?Carbon $hoje = null, ?Carbon $vencimentoDe = null, ?Carbon $vencimentoAte = null): float
    {
        $hoje ??= Carbon::today();

        $query = self::openReceitaBase()
            ->where('contas.data_vencimento', '<', $hoje->toDateString());

        if ($vencimentoDe) {
            $query->where('contas.data_vencimento', '>=', $vencimentoDe->toDateString());
        }
        if ($vencimentoAte) {
            $query->where('contas.data_vencimento', '<=', $vencimentoAte->toDateString());
        }

        return (float) $query->sum(DB::raw(self::saldoExpr()));
    }

    public static function sumSaldoAVencer(Carbon $from, Carbon $to, Carbon $hoje): float
    {
        $inicioEfetivo = $from->greaterThan($hoje) ? $from : $hoje;

        if ($inicioEfetivo->greaterThan($to)) {
            return 0.0;
        }

        return (float) self::openReceitaBase()
            ->where('contas.data_vencimento', '>=', $inicioEfetivo->toDateString())
            ->where('contas.data_vencimento', '<=', $to->toDateString())
            ->sum(DB::raw(self::saldoExpr()));
    }

    public static function sumFaturamento(Carbon $from, Carbon $to): float
    {
        $query = DB::table('conta_pagamentos')
            ->whereNull('deleted_at')
            ->whereBetween('data_pagamento', [$from->toDateString(), $to->toDateString()]);

        InstituicaoContext::applyToQuery($query, 'conta_pagamentos');

        return (float) $query->sum('valor_pago');
    }
}

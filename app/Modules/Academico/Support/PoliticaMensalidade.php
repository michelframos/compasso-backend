<?php

namespace App\Modules\Academico\Support;

use Carbon\Carbon;

/**
 * @phpstan-type PoliticaResolvida array{
 *   modo: 'automatico'|'formulario'|'duracao_turma',
 *   dia_vencimento: int,
 *   quantidade_parcelas: int,
 *   valor: float,
 *   inicio_parcelas: 'mes_matricula'|'mes_seguinte'
 * }
 */
final class PoliticaMensalidade
{
    public const MODO_AUTOMATICO = 'automatico';

    public const MODO_FORMULARIO = 'formulario';

    public const MODO_DURACAO_TURMA = 'duracao_turma';

    public const MODOS = [
        self::MODO_AUTOMATICO,
        self::MODO_FORMULARIO,
        self::MODO_DURACAO_TURMA,
    ];

    public const INICIO_MES_MATRICULA = 'mes_matricula';

    public const INICIO_MES_SEGUINTE = 'mes_seguinte';

    public const INICIOS = [
        self::INICIO_MES_MATRICULA,
        self::INICIO_MES_SEGUINTE,
    ];

    /**
     * @param  object{
     *   modo_geracao_mensalidades?: ?string,
     *   dia_vencimento_mensalidade?: ?int,
     *   quantidade_parcelas_mensalidade?: ?int,
     *   inicio_parcelas_mensalidade?: ?string,
     *   valor_mensalidade?: float|int|string|null,
     *   data_inicio?: mixed,
     *   data_fim?: mixed
     * }|null  $turma
     * @param  object{
     *   modo_geracao_mensalidades?: ?string,
     *   dia_vencimento_mensalidade?: ?int,
     *   quantidade_parcelas_mensalidade?: ?int,
     *   inicio_parcelas_mensalidade?: ?string
     * }|null  $instituicao
     * @return PoliticaResolvida
     */
    public static function resolver(?object $turma, ?object $instituicao, ?string $dataMatricula = null): array
    {
        $modoTurma = $turma->modo_geracao_mensalidades ?? null;
        $modoInst = $instituicao->modo_geracao_mensalidades ?? null;

        $modo = in_array($modoTurma, self::MODOS, true)
            ? $modoTurma
            : (in_array($modoInst, self::MODOS, true)
                ? $modoInst
                : self::MODO_FORMULARIO);

        $dia = $turma->dia_vencimento_mensalidade
            ?? $instituicao->dia_vencimento_mensalidade
            ?? 10;

        $qtdFixa = $turma->quantidade_parcelas_mensalidade
            ?? $instituicao->quantidade_parcelas_mensalidade
            ?? 12;

        $inicioParcelas = self::resolverInicioParcelas($turma, $instituicao);

        $qtd = (int) $qtdFixa;

        if ($modo === self::MODO_DURACAO_TURMA) {
            $qtdDuracao = self::parcelasPorDuracao($turma, $dataMatricula, $inicioParcelas);
            if ($qtdDuracao !== null) {
                $qtd = $qtdDuracao;
            }
        }

        return [
            'modo' => $modo,
            'dia_vencimento' => max(1, min(31, (int) $dia)),
            'quantidade_parcelas' => max(1, min(24, $qtd)),
            'valor' => round((float) ($turma->valor_mensalidade ?? 0), 2),
            'inicio_parcelas' => $inicioParcelas,
        ];
    }

    /**
     * @param  object{inicio_parcelas_mensalidade?: ?string}|null  $turma
     * @param  object{inicio_parcelas_mensalidade?: ?string}|null  $instituicao
     */
    public static function resolverInicioParcelas(?object $turma, ?object $instituicao): string
    {
        $inicioTurma = $turma->inicio_parcelas_mensalidade ?? null;
        if (in_array($inicioTurma, self::INICIOS, true)) {
            return $inicioTurma;
        }

        $inicioInst = $instituicao->inicio_parcelas_mensalidade ?? null;
        if (in_array($inicioInst, self::INICIOS, true)) {
            return $inicioInst;
        }

        return self::INICIO_MES_MATRICULA;
    }

    /**
     * Se mes_seguinte, primeiro dia do mês seguinte à data base; senão a própria data base.
     */
    public static function dataInicioLote(string $dataBase, string $inicioParcelas): string
    {
        try {
            $base = Carbon::parse($dataBase);
        } catch (\Throwable) {
            return $dataBase;
        }

        if ($inicioParcelas === self::INICIO_MES_SEGUINTE) {
            return $base->copy()->startOfMonth()->addMonth()->format('Y-m-d');
        }

        return $base->format('Y-m-d');
    }

    /**
     * Início efetivo do lote em duracao_turma (antes de aplicar mes_seguinte):
     * matrícula antes do início da turma → data_inicio da turma; senão data da matrícula.
     */
    public static function inicioEfetivoDuracao(?object $turma, ?string $dataMatricula): ?string
    {
        $dataInicioTurma = self::toCarbon($turma->data_inicio ?? null);
        $dataMat = self::toCarbon($dataMatricula);

        if ($dataMat !== null && $dataInicioTurma !== null && $dataMat->lt($dataInicioTurma->copy()->startOfDay())) {
            return $dataInicioTurma->format('Y-m-d');
        }

        if ($dataMat !== null) {
            return $dataMat->format('Y-m-d');
        }

        if ($dataInicioTurma !== null) {
            return $dataInicioTurma->format('Y-m-d');
        }

        return null;
    }

    /**
     * Meses inclusivos do início efetivo (após política de início das parcelas) até data_fim da turma.
     * Matrícula antes do início → data_inicio..data_fim; senão → data da matrícula..data_fim.
     * Com mes_seguinte, o início do lote é deslocado e a quantidade é recalculada.
     */
    public static function parcelasPorDuracao(
        ?object $turma,
        ?string $dataMatricula,
        string $inicioParcelas = self::INICIO_MES_MATRICULA,
    ): ?int {
        if ($turma === null) {
            return null;
        }

        $dataFim = self::toCarbon($turma->data_fim ?? null);
        if ($dataFim === null) {
            return null;
        }

        $inicioEfetivo = self::inicioEfetivoDuracao($turma, $dataMatricula);
        if ($inicioEfetivo === null) {
            return null;
        }

        $dataInicioLote = self::dataInicioLote($inicioEfetivo, $inicioParcelas);
        $inicio = Carbon::parse($dataInicioLote)->startOfMonth();
        $fim = $dataFim->copy()->startOfMonth();

        if ($fim->lt($inicio)) {
            return null;
        }

        return (int) $inicio->diffInMonths($fim) + 1;
    }

    private static function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

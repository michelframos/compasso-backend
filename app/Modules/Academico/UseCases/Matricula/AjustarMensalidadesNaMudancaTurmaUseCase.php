<?php

namespace App\Modules\Academico\UseCases\Matricula;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\PoliticaMensalidade;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AjustarMensalidadesNaMudancaTurmaUseCase
{
    public function __construct(
        private readonly GerarMensalidadesDaMatriculaUseCase $gerarMensalidades,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    /**
     * @param  array{
     *   reajustar_mensalidades?: bool,
     *   gerar_novas_mensalidades?: bool,
     *   valor?: float|int|string|null,
     *   quantidade_parcelas?: int|null,
     *   dia_vencimento?: int|null,
     *   data_inicio?: string|null,
     *   id_categoria?: int|null,
     *   observacoes?: string|null
     * }  $opcoes
     * @return array{reajustadas: int, geradas: int}
     */
    public function execute(Matricula $matricula, Turma $turmaDestino, array $opcoes = []): array
    {
        $instituicao = InstituicaoContext::instituicao()
            ?? Instituicao::query()->find($matricula->id_instituicao);

        if ($instituicao === null || ! $this->entitlements->permiteModulo($instituicao, 'financeiro')) {
            return ['reajustadas' => 0, 'geradas' => 0];
        }

        $reajustar = (bool) ($opcoes['reajustar_mensalidades'] ?? false);
        $gerarNovas = (bool) ($opcoes['gerar_novas_mensalidades'] ?? false);

        if (! $reajustar && ! $gerarNovas) {
            return ['reajustadas' => 0, 'geradas' => 0];
        }

        $politica = PoliticaMensalidade::resolver($turmaDestino, $instituicao);
        $valor = isset($opcoes['valor']) && $opcoes['valor'] !== null && $opcoes['valor'] !== ''
            ? round((float) $opcoes['valor'], 2)
            : $politica['valor'];

        // Reajuste usa o mês da mudança (hoje); data_inicio vale só para o novo lote.
        $referenciaReajuste = now()->startOfMonth();
        $dataInicioLote = $opcoes['data_inicio'] ?? $referenciaReajuste->toDateString();

        return DB::transaction(function () use ($matricula, $reajustar, $gerarNovas, $valor, $politica, $opcoes, $referenciaReajuste, $dataInicioLote) {
            $reajustadas = 0;

            if ($reajustar && $valor > 0) {
                $contas = Conta::query()
                    ->where('id_matricula', $matricula->id)
                    ->whereIn('status', ['pendente', 'vencido', 'pago_parcialmente'])
                    ->where(function ($q) use ($referenciaReajuste) {
                        $q->where('ano_referencia', '>', $referenciaReajuste->year)
                            ->orWhere(function ($q2) use ($referenciaReajuste) {
                                $q2->where('ano_referencia', $referenciaReajuste->year)
                                    ->where('mes_referencia', '>=', $referenciaReajuste->month);
                            });
                    })
                    ->get();

                foreach ($contas as $conta) {
                    $conta->update(['valor' => $valor]);
                    $reajustadas++;
                }
            }

            $geradas = 0;

            if ($gerarNovas && $valor > 0) {
                $qtd = (int) ($opcoes['quantidade_parcelas'] ?? $politica['quantidade_parcelas']);
                $dia = (int) ($opcoes['dia_vencimento'] ?? $politica['dia_vencimento']);

                $contas = $this->gerarMensalidades->execute($matricula, [
                    'valor' => $valor,
                    'quantidade_parcelas' => $qtd,
                    'dia_vencimento' => $dia,
                    'data_inicio' => $dataInicioLote,
                    'id_categoria' => $opcoes['id_categoria'] ?? null,
                    'observacoes' => $opcoes['observacoes']
                        ?? "Geração após mudança de turma (matrícula #{$matricula->id})",
                ]);

                $geradas = count($contas);
            }

            return ['reajustadas' => $reajustadas, 'geradas' => $geradas];
        });
    }
}

<?php

namespace App\Modules\Financeiro\UseCases\Conta;

use App\Modules\Core\Domain\Recorrencia\RecorrenciaResolver;
use App\Modules\Financeiro\Models\Conta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateContaUseCase
{
    public function __construct(
        private readonly RecorrenciaResolver $recorrencias,
    ) {}

    /**
     * Cria a conta ou, quando há repetição, uma conta por ocorrência.
     *
     * @return Collection<int, Conta>
     */
    public function execute(array $data): Collection
    {
        $frequencia = $data['repeticao'] ?? null;
        $quantidade = (int) ($data['quantidade_repeticoes'] ?? 1);
        unset($data['repeticao'], $data['quantidade_repeticoes']);

        if (! $frequencia || $quantidade <= 1) {
            return collect([Conta::create($data)]);
        }

        $recorrencia = $this->recorrencias->resolve($frequencia);
        $primeiroVencimento = Carbon::parse($data['data_vencimento']);

        return DB::transaction(fn () => collect(range(0, $quantidade - 1))->map(function (int $i) use ($data, $recorrencia, $primeiroVencimento, $quantidade, $frequencia) {
            $vencimento = $recorrencia->avancar($primeiroVencimento, $i);

            $parcela = array_merge($data, [
                'data_vencimento' => $vencimento->toDateString(),
                'numero_parcela' => $i + 1,
                'quantidade_parcelas' => $quantidade,
            ]);

            if ($frequencia === 'mensal') {
                $parcela['mes_referencia'] = $vencimento->month;
                $parcela['ano_referencia'] = $vencimento->year;
            }

            return Conta::create($parcela);
        }));
    }
}

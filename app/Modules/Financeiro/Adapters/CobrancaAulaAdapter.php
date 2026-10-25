<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\CobrancaAulaPort;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Database\Eloquent\Builder;

class CobrancaAulaAdapter implements CobrancaAulaPort
{
    private const CANCELADO = 'cancelado';

    public function aulasComCobranca(array $idsAulas): array
    {
        if ($idsAulas === []) {
            return [];
        }

        return $this->ativas()
            ->whereIn('id_aula_turma', $idsAulas)
            ->distinct()
            ->pluck('id_aula_turma')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function transferir(int $idAulaOrigem, int $idAulaDestino, string $observacao): void
    {
        $this->ativas()->where('id_aula_turma', $idAulaOrigem)->get()
            ->each(fn (Conta $conta) => $conta->update([
                'id_aula_turma' => $idAulaDestino,
                'observacoes' => $this->anotar($conta, $observacao),
            ]));
    }

    public function cancelar(int $idAula, string $observacao): bool
    {
        $contas = $this->ativas()->where('id_aula_turma', $idAula)->get();

        if ($contas->contains(fn (Conta $conta) => $conta->pagamentos()->exists())) {
            return false;
        }

        $contas->each(fn (Conta $conta) => $conta->update([
            'status' => self::CANCELADO,
            'observacoes' => $this->anotar($conta, $observacao),
        ]));

        return true;
    }

    private function ativas(): Builder
    {
        return Conta::query()->where('status', '!=', self::CANCELADO);
    }

    private function anotar(Conta $conta, string $observacao): string
    {
        return trim(($conta->observacoes ? $conta->observacoes."\n" : '').$observacao);
    }
}

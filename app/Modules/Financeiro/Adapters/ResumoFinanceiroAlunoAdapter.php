<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Contracts\ResumoFinanceiroAlunoPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Queries\Situacao\Atrasadas;
use Illuminate\Database\Eloquent\Builder;

class ResumoFinanceiroAlunoAdapter implements ResumoFinanceiroAlunoPort
{
    public function __construct(private readonly PlanoEntitlementResolverInterface $planos) {}

    public function resumir(int $idAluno): array
    {
        if (! $this->moduloLiberado('financeiro')) {
            return $this->vazio();
        }

        $hoje = now()->toDateString();
        $abertas = $this->abertas($idAluno);
        $atrasadas = clone $abertas;
        (new Atrasadas)->aplicar($atrasadas, $hoje);

        $proxima = (clone $abertas)->orderBy('data_vencimento')->orderBy('id')->first();

        return [
            'disponivel' => true,
            'proxima_mensalidade' => $proxima ? $this->mensalidade($proxima, $hoje) : null,
            'em_aberto' => (clone $abertas)->count(),
            'atrasadas' => (clone $atrasadas)->count(),
            'valor_em_aberto' => $this->soma($abertas),
            'valor_atrasado' => $this->soma($atrasadas),
        ];
    }

    private function abertas(int $idAluno): Builder
    {
        return Conta::query()
            ->where('id_aluno', $idAluno)
            ->where('tipo', 'receita')
            ->whereNotIn('status', ['pago', 'cancelado']);
    }

    /** @return array{id: int, descricao: string, valor: float, data_vencimento: string, status: string, situacao: string} */
    private function mensalidade(Conta $conta, string $hoje): array
    {
        $vencimento = $conta->data_vencimento?->toDateString() ?? '';

        return [
            'id' => $conta->id,
            'descricao' => $conta->descricao,
            'valor' => round((float) $conta->valor, 2),
            'data_vencimento' => $vencimento,
            'status' => $conta->status,
            'situacao' => match (true) {
                $vencimento !== '' && $vencimento < $hoje => 'atrasada',
                $vencimento === $hoje => 'vence_hoje',
                default => 'a_vencer',
            },
        ];
    }

    private function soma(Builder $query): float
    {
        return round((float) (clone $query)->sum('valor'), 2);
    }

    private function moduloLiberado(string $modulo): bool
    {
        $instituicao = InstituicaoContext::instituicao();

        return $instituicao !== null && $this->planos->permiteModulo($instituicao, $modulo);
    }

    /** @return array{disponivel: bool, proxima_mensalidade: null, em_aberto: int, atrasadas: int, valor_em_aberto: float, valor_atrasado: float} */
    private function vazio(): array
    {
        return [
            'disponivel' => false,
            'proxima_mensalidade' => null,
            'em_aberto' => 0,
            'atrasadas' => 0,
            'valor_em_aberto' => 0.0,
            'valor_atrasado' => 0.0,
        ];
    }
}

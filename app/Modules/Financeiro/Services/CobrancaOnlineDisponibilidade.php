<?php

namespace App\Modules\Financeiro\Services;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Financeiro\Models\Conta;

/**
 * Regras de disponibilidade da cobrança online (billing ativo + subconta ativa + meios configurados).
 * Registrado como scoped: memoiza instituições e o estado do gateway durante a requisição.
 */
class CobrancaOnlineDisponibilidade
{
    /** @var array<int, Instituicao|null> */
    private array $instituicoes = [];

    private ?bool $billingDisponivel = null;

    public function __construct(private readonly BillingGatewayClient $gateway) {}

    public function billingDisponivel(): bool
    {
        return $this->billingDisponivel ??= $this->gateway->isAvailable();
    }

    public function instituicao(int $idInstituicao): ?Instituicao
    {
        if (! array_key_exists($idInstituicao, $this->instituicoes)) {
            $this->instituicoes[$idInstituicao] = Instituicao::query()->find($idInstituicao);
        }

        return $this->instituicoes[$idInstituicao];
    }

    /** Conta a receber do aluno ainda com saldo a pagar. */
    public function contaCobravel(Conta $conta, ?float $saldoDevedor = null): bool
    {
        return in_array($conta->tipo, ['receita', 'receber'], true)
            && ! in_array($conta->status, ['pago', 'cancelado'], true)
            && ($saldoDevedor ?? $conta->saldoDevedor()) > 0;
    }

    /**
     * @return array{disponivel: bool, meios: list<string>}
     */
    public function paraConta(Conta $conta, ?float $saldoDevedor = null): array
    {
        $instituicao = $conta->id_instituicao !== null ? $this->instituicao((int) $conta->id_instituicao) : null;

        if ($instituicao === null || ! $this->billingDisponivel() || ! $instituicao->subcontaAtiva()) {
            return ['disponivel' => false, 'meios' => []];
        }

        $meios = array_values(array_intersect($instituicao->meiosCobrancaOnline(), Instituicao::MEIOS_COBRANCA_DISPONIVEIS));

        return [
            'disponivel' => $meios !== [] && $this->contaCobravel($conta, $saldoDevedor),
            'meios' => $meios,
        ];
    }
}

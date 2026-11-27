<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobrança online (Centralizador de Pagamentos) de uma conta a receber do aluno.
 * Diferente da assinatura SaaS, o valor vai para a subconta da escola (sem retain_on_platform).
 */
class ContaCobrancaGateway extends Model
{
    use PertenceAInstituicao;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    /** Ainda aguardam desfecho: o aluno pode pagar e o job deve continuar sincronizando. */
    public const STATUS_ABERTOS = [
        self::STATUS_PENDING,
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_OVERDUE,
    ];

    public const STATUS_FINAIS = [
        self::STATUS_PAID,
        self::STATUS_CANCELED,
        self::STATUS_FAILED,
    ];

    public const METODOS = ['pix', 'credit_card', 'boleto'];

    protected $table = 'contas_cobrancas_gateway';

    protected $fillable = [
        'id_conta',
        'id_instituicao',
        'gateway_payment_uuid',
        'method',
        'amount',
        'status',
        'invoice_url',
        'pix_copy_paste',
        'pix_qr_code',
        'last_error',
        'id_conta_pagamento',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'id_conta');
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(ContaPagamento::class, 'id_conta_pagamento');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::STATUS_FINAIS, true);
    }

    public function isAberta(): bool
    {
        return in_array($this->status, self::STATUS_ABERTOS, true);
    }

    /**
     * Cobranças ainda em andamento (não pagas, canceladas ou com falha).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNaoFinais(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::STATUS_FINAIS);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIsAberta(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_ABERTOS);
    }
}

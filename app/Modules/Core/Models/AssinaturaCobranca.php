<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Assinatura SaaS da escola cobrada via Centralizador de Pagamentos (retain_on_platform).
 */
class AssinaturaCobranca extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    /** Status que ainda aguardam desfecho (pagamento / processamento no gateway). */
    public const STATUS_ABERTOS = [
        self::STATUS_PENDING,
        self::STATUS_AWAITING_PAYMENT,
    ];

    /** Status em que a assinatura está vigente (conta como "tem assinatura"). */
    public const STATUS_VIGENTES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAST_DUE,
    ];

    public const STATUS_FINAIS = [
        self::STATUS_CANCELED,
        self::STATUS_FAILED,
    ];

    public const METODOS = ['pix', 'credit_card', 'boleto'];

    public const CYCLE_MONTHLY = 'MONTHLY';

    protected $table = 'assinaturas_cobranca';

    protected $fillable = [
        'id_instituicao',
        'id_plano_assinatura',
        'gateway_subscription_uuid',
        'method',
        'cycle',
        'amount',
        'status',
        'invoice_url',
        'next_due_date',
        'customer_email',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due_date' => 'date',
        ];
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'id_instituicao');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoAssinatura::class, 'id_plano_assinatura');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::STATUS_FINAIS, true);
    }

    public function isAberta(): bool
    {
        return in_array($this->status, self::STATUS_ABERTOS, true);
    }

    public function isVigente(): bool
    {
        return in_array($this->status, self::STATUS_VIGENTES, true);
    }

    /**
     * Assinaturas ainda em andamento: abertas ou vigentes (não finais).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNaoFinais(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::STATUS_FINAIS);
    }
}

<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoAssinatura extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    protected $table = 'solicitacoes_assinatura';

    protected $fillable = [
        'id_instituicao',
        'id_plano_assinatura',
        'id_usuario',
        'status',
        'observacao',
        'id_aprovado_por',
        'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'aprovado_em' => 'datetime',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_aprovado_por');
    }

    public function isPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }
}

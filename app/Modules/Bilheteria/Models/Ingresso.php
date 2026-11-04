<?php

namespace App\Modules\Bilheteria\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingresso extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_VENDIDO = 'vendido';

    public const STATUS_UTILIZADO = 'utilizado';

    public const PAGAMENTO_AGUARDANDO = 'aguardando_pagamento';

    public const PAGAMENTO_PAGO = 'pago';

    public const PAGAMENTO_CANCELADO = 'cancelado';

    protected $table = 'ingressos';

    protected $fillable = [
        'id_instituicao',
        'id_espetaculo',
        'id_template_ingresso',
        'numero',
        'codigo_qr',
        'valor',
        'status',
        'cortesia',
        'status_pagamento',
        'id_conta',
        'id_cupom',
        'comprador_nome',
        'comprador_documento',
        'utilizado_em',
        'conteudo_gerado',
        'fundo_path',
    ];

    protected $casts = [
        'valor' => 'float',
        'cortesia' => 'boolean',
        'utilizado_em' => 'datetime',
    ];

    public function espetaculo()
    {
        return $this->belongsTo(Espetaculo::class, 'id_espetaculo');
    }

    public function template()
    {
        return $this->belongsTo(TemplateIngresso::class, 'id_template_ingresso');
    }

    public function cupom()
    {
        return $this->belongsTo(CupomIngresso::class, 'id_cupom');
    }

    public function conta()
    {
        return $this->belongsTo(Conta::class, 'id_conta');
    }

    public function rotuloStatus(): string
    {
        if ($this->status === self::STATUS_VENDIDO && $this->cortesia) {
            return 'vendido (cortesia)';
        }

        return (string) $this->status;
    }
}

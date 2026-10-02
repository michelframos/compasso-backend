<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ContaPagamento extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    protected $fillable = [
        'id_instituicao',
        'id_conta',
        'valor_pago',
        'data_pagamento',
        'forma_pagamento',
        'observacoes'
    ];

    public function conta()
    {
        return $this->belongsTo(Conta::class, 'id_conta');
    }
}

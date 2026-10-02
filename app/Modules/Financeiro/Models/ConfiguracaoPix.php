<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoPix extends Model
{
    use PertenceAInstituicao;

    protected $table = 'configuracoes_pix';

    protected $fillable = [
        'id_instituicao',
        'chave_pix',
        'tipo_chave',
        'nome_beneficiario',
        'cidade',
        'exibir_qrcode',
    ];

    protected $casts = [
        'exibir_qrcode' => 'boolean',
    ];
}

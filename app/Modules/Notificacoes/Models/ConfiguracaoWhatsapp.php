<?php

namespace App\Modules\Notificacoes\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoWhatsapp extends Model
{
    use PertenceAInstituicao;

    protected $table = 'configuracoes_whatsapp';

    protected $fillable = [
        'id_instituicao',
        'instance_name',
        'status',
    ];
}

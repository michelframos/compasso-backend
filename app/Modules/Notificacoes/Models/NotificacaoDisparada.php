<?php

namespace App\Modules\Notificacoes\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

class NotificacaoDisparada extends Model
{
    use PertenceAInstituicao;

    protected $table = 'notificacoes_disparadas';

    protected $fillable = [
        'id_instituicao',
        'configuracao_notificacao_id',
        'referencia_type',
        'referencia_id',
        'numero_whatsapp',
        'status',
        'tentativas',
        'disparado_em',
    ];

    protected $casts = [
        'disparado_em' => 'datetime',
        'tentativas' => 'integer',
    ];

    public function configuracaoNotificacao()
    {
        return $this->belongsTo(ConfiguracaoNotificacao::class, 'configuracao_notificacao_id');
    }

    public function referencia()
    {
        return $this->morphTo('referencia');
    }
}

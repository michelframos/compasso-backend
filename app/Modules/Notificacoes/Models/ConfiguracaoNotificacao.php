<?php

namespace App\Modules\Notificacoes\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class ConfiguracaoNotificacao extends Model
{
    use PertenceAInstituicao;

    protected $table = 'configuracoes_notificacoes';

    protected $fillable = [
        'id_instituicao',
        'modulo',
        'tipo',
        'ativo',
        'dias_antecedencia',
        'intervalo_repeticao',
        'max_repeticoes',
        'template_mensagem',
        'horario_envio',
        'ultimo_processamento',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'dias_antecedencia' => 'integer',
        'intervalo_repeticao' => 'integer',
        'max_repeticoes' => 'integer',
        'ultimo_processamento' => 'datetime',
    ];

    public function notificacoesDisparadas()
    {
        return $this->hasMany(NotificacaoDisparada::class, 'configuracao_notificacao_id');
    }

    protected function horarioEnvio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value)->format('H:i') : null,
        );
    }
}

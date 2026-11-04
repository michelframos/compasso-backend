<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Espetaculo extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    /** Espetáculos que não recebem mais ensaios. */
    public const STATUS_ENCERRADOS = ['concluido', 'cancelado'];

    protected $fillable = [
        'id_instituicao',
        'titulo',
        'data_evento',
        'hora_evento',
        'local',
        'status',
        'observacoes',
        'contrato_id',
        'validade_ingresso_inicio',
        'validade_ingresso_fim',
        'id_template_ingresso',
        'valor_ingresso_padrao',
    ];

    protected $casts = [
        'data_evento' => 'date',
        'validade_ingresso_inicio' => 'datetime',
        'validade_ingresso_fim' => 'datetime',
        'valor_ingresso_padrao' => 'float',
    ];

    public function apresentacoes()
    {
        return $this->hasMany(Apresentacao::class, 'id_espetaculo');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }

    public function templateIngresso()
    {
        return $this->belongsTo(\App\Modules\Bilheteria\Models\TemplateIngresso::class, 'id_template_ingresso');
    }

    public function ingressos()
    {
        return $this->hasMany(\App\Modules\Bilheteria\Models\Ingresso::class, 'id_espetaculo');
    }

    /** Janela de validade do ingresso (fallback: dia do evento). */
    public function janelaValidadeIngresso(): array
    {
        if ($this->validade_ingresso_inicio && $this->validade_ingresso_fim) {
            return [$this->validade_ingresso_inicio, $this->validade_ingresso_fim];
        }

        $inicio = $this->data_evento->copy()->startOfDay();
        $fim = $this->data_evento->copy()->endOfDay();

        return [$inicio, $fim];
    }

    /** Professor, aluno e responsável só veem espetáculos com alguma apresentação visível para eles. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        if (! in_array($user->role, ['professor', 'aluno', 'responsavel'], true)) {
            return $query;
        }

        return $query->whereHas('apresentacoes', fn (Builder $a) => $a->visivelPara($user));
    }
}

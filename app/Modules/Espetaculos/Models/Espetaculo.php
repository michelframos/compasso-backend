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

    protected $fillable = ['id_instituicao', 'titulo', 'data_evento', 'local', 'status', 'observacoes', 'contrato_id'];

    public function apresentacoes()
    {
        return $this->hasMany(Apresentacao::class, 'id_espetaculo');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
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

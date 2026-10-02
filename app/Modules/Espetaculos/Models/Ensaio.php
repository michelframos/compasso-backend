<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ensaio extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    /** Relações exibidas pelo EnsaioResource. */
    public const DETALHES = ['apresentacao.espetaculo', 'apresentacao.turma', 'professor.usuario'];

    protected $table = 'ensaios';

    protected $fillable = [
        'id_instituicao',
        'id_apresentacao',
        'id_professor',
        'data',
        'hora_inicio',
        'hora_termino',
        'local',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function apresentacao()
    {
        return $this->belongsTo(Apresentacao::class, 'id_apresentacao');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    /** Professor vê os ensaios que conduz e os das apresentações visíveis para ele. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        if ($user->role !== 'professor') {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('id_professor', $user->professor?->id ?? 0)
            ->orWhereHas('apresentacao', fn (Builder $a) => $a->visivelPara($user)));
    }
}

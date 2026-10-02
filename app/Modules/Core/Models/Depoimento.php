<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depoimento extends Model
{
    use SoftDeletes;

    protected $table = 'depoimentos';

    protected $fillable = [
        'nome',
        'cargo',
        'escola',
        'conteudo',
        'avatar_url',
        'ordem',
        'aprovado',
        'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'aprovado' => 'boolean',
            'aprovado_em' => 'datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function scopeAprovados(Builder $query): Builder
    {
        return $query->where('aprovado', true)->orderBy('ordem')->orderBy('id');
    }
}

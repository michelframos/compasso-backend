<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteModulo extends Model
{
    use SoftDeletes;

    protected $table = 'site_modulos';

    protected $fillable = [
        'nome',
        'descricao',
        'recursos',
        'icone',
        'ordem',
        'aprovado',
        'aprovado_em',
    ];

    protected function casts(): array
    {
        return [
            'recursos' => 'array',
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

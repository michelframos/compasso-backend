<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanoAssinatura extends Model
{
    use SoftDeletes;

    protected $table = 'planos_assinatura';

    protected $fillable = [
        'slug',
        'nome',
        'descricao',
        'preco_mensal',
        'limite_alunos',
        'modulos',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'limite_alunos' => 'integer',
            'modulos' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function instituicoes(): HasMany
    {
        return $this->hasMany(Instituicao::class, 'id_plano_assinatura');
    }

    /**
     * Planos ativos para vitrine pública e solicitação, com destaque no plano intermediário.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function ativosParaVitrine()
    {
        $planos = static::query()
            ->where('ativo', true)
            ->orderBy('preco_mensal')
            ->get();

        $count = $planos->count();
        $destaqueIndex = $count >= 3 ? intdiv($count, 2) : ($count === 2 ? 1 : 0);

        $planos->values()->each(function (self $plano, int $index) use ($destaqueIndex): void {
            $plano->setAttribute('destaque', $index === $destaqueIndex);
        });

        return $planos;
    }
}

<?php

namespace App\Modules\Core\Models\Concerns;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\Scopes\InstituicaoScope;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para models de domínio com coluna id_instituicao.
 *
 * Aplica GlobalScope quando InstituicaoContext está definido e preenche
 * id_instituicao automaticamente em novos registros.
 */
trait PertenceAInstituicao
{
    public static function bootPertenceAInstituicao(): void
    {
        static::addGlobalScope(new InstituicaoScope);

        static::creating(function (Model $model): void {
            if (empty($model->id_instituicao) && InstituicaoContext::has()) {
                $model->id_instituicao = InstituicaoContext::id();
            }
        });
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'id_instituicao');
    }

    public function scopeWithoutInstituicaoScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(InstituicaoScope::class);
    }
}

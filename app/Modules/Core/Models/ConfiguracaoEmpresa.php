<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * @deprecated Use Instituicao. Mantido para compatibilidade da API configuracao-empresa.
 *             Representa a instituição ativa (InstituicaoContext) ou default (slug: default).
 */
class ConfiguracaoEmpresa extends Instituicao
{
    protected $table = 'instituicoes';

    protected static function booted(): void
    {
        static::addGlobalScope('configuracao_empresa_instituicao', function (Builder $builder): void {
            $table = $builder->getModel()->getTable();

            if (InstituicaoContext::has()) {
                $builder->where($table.'.id', InstituicaoContext::id());
            } else {
                $builder->where($table.'.slug', 'default');
            }
        });

        static::creating(function (ConfiguracaoEmpresa $model): void {
            $model->slug ??= InstituicaoContext::slug() ?? 'default';
            $model->status ??= self::STATUS_ATIVO;
        });
    }
}

<?php

namespace App\Modules\Pessoas\Support;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;

/** A flag fica na instituição, que substituiu a antiga configuracoes_empresa. */
class EdicaoMedidasDoAluno
{
    public static function permitida(): bool
    {
        if (! InstituicaoContext::has()) {
            return false;
        }

        return (bool) Instituicao::query()
            ->whereKey(InstituicaoContext::id())
            ->value('permitir_aluno_editar_medidas');
    }
}

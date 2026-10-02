<?php

namespace App\Modules\Core\UseCases\Instituicao;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;

class ListUserInstituicoesUseCase
{
    /**
     * @return Collection<int, Instituicao>
     */
    public function execute(User $user): Collection
    {
        return $user->instituicoesAtivas()->get();
    }
}

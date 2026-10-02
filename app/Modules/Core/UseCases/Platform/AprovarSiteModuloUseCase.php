<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Models\SiteModulo;

class AprovarSiteModuloUseCase
{
    public function __construct(
        private readonly SiteModuloRepositoryInterface $modulos,
    ) {}

    public function execute(SiteModulo $modulo): SiteModulo
    {
        return $this->modulos->update($modulo, [
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);
    }
}

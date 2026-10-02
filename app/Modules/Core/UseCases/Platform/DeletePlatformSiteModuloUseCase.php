<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Models\SiteModulo;

class DeletePlatformSiteModuloUseCase
{
    public function __construct(
        private readonly SiteModuloRepositoryInterface $modulos,
    ) {}

    public function execute(SiteModulo $modulo): void
    {
        $this->modulos->delete($modulo);
    }
}

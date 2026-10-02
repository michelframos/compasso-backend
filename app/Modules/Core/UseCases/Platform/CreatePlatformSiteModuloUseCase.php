<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Models\SiteModulo;
use App\Modules\Core\Support\SiteModuloIcones;

class CreatePlatformSiteModuloUseCase
{
    public function __construct(
        private readonly SiteModuloRepositoryInterface $modulos,
    ) {}

    public function execute(array $data): SiteModulo
    {
        $data['aprovado'] = false;
        $data['aprovado_em'] = null;
        $data['ordem'] ??= 0;
        $data['recursos'] = array_values($data['recursos'] ?? []);
        $data['icone'] = SiteModuloIcones::normalizar($data['icone'] ?? null);

        return $this->modulos->create($data);
    }
}

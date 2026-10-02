<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Models\SiteModulo;
use App\Modules\Core\Support\SiteModuloIcones;

class UpdatePlatformSiteModuloUseCase
{
    public function __construct(
        private readonly SiteModuloRepositoryInterface $modulos,
    ) {}

    public function execute(SiteModulo $modulo, array $data): SiteModulo
    {
        unset($data['aprovado'], $data['aprovado_em']);

        if (array_key_exists('recursos', $data)) {
            $data['recursos'] = array_values($data['recursos'] ?? []);
        }

        if (array_key_exists('icone', $data)) {
            $data['icone'] = SiteModuloIcones::normalizar($data['icone']);
        }

        return $this->modulos->update($modulo, $data);
    }
}

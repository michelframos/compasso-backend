<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;

class UpdatePlatformPlanoAssinaturaUseCase
{
    public function __construct(
        private readonly PlanoAssinaturaRepositoryInterface $planos,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    public function execute(PlanoAssinatura $plano, array $data): PlanoAssinatura
    {
        if (array_key_exists('modulos', $data)) {
            $data['modulos'] = $this->entitlements->normalizeModulos($data['modulos']);
        }

        return $this->planos->update($plano, $data);
    }
}

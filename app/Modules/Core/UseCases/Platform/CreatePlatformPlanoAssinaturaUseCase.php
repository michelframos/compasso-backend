<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;

class CreatePlatformPlanoAssinaturaUseCase
{
    public function __construct(
        private readonly PlanoAssinaturaRepositoryInterface $planos,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    public function execute(array $data): PlanoAssinatura
    {
        $data['ativo'] ??= true;
        $data['modulos'] = $this->entitlements->normalizeModulos($data['modulos'] ?? []);

        return $this->planos->create($data);
    }
}

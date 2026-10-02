<?php

namespace App\Modules\Core\Observers;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\CachedPlanoEntitlementResolver;

class InstituicaoEntitlementObserver
{
    public function __construct(
        private readonly CachedPlanoEntitlementResolver $entitlements,
    ) {}

    public function updated(Instituicao $instituicao): void
    {
        $this->entitlements->esquecer($instituicao);
    }

    public function deleted(Instituicao $instituicao): void
    {
        $this->entitlements->esquecer($instituicao);
    }
}

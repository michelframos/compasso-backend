<?php

namespace App\Modules\Core\Observers;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\CachedPlanoEntitlementResolver;

class PlanoAssinaturaEntitlementObserver
{
    public function __construct(
        private readonly CachedPlanoEntitlementResolver $entitlements,
    ) {}

    public function updated(PlanoAssinatura $plano): void
    {
        $this->esquecerInstituicoesDoPlano($plano);
    }

    public function deleted(PlanoAssinatura $plano): void
    {
        $this->esquecerInstituicoesDoPlano($plano);
    }

    private function esquecerInstituicoesDoPlano(PlanoAssinatura $plano): void
    {
        Instituicao::query()
            ->where('id_plano_assinatura', $plano->getKey())
            ->get(['id', 'updated_at'])
            ->each(fn (Instituicao $instituicao) => $this->entitlements->esquecer($instituicao));
    }
}

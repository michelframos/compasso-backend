<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;

class PlanoEntitlementResolver implements PlanoEntitlementResolverInterface
{
    /**
     * @return list<string>
     */
    public function catalogKeys(): array
    {
        return array_keys(config('platform.modulos_app', []));
    }

    /**
     * @return array<string, array{label: string, descricao: string}>
     */
    public function catalog(): array
    {
        /** @var array<string, array{label: string, descricao: string}> */
        return config('platform.modulos_app', []);
    }

    public function labelFor(string $key): ?string
    {
        $catalog = $this->catalog();

        return $catalog[$key]['label'] ?? null;
    }

    /**
     * @return array{modulos: list<string>, limite_alunos: int|null, em_trial: bool}
     */
    public function resolve(Instituicao $instituicao): array
    {
        if ($this->isTrialUnlocked($instituicao)) {
            return [
                'modulos' => $this->catalogKeys(),
                'limite_alunos' => null,
                'em_trial' => true,
            ];
        }

        $instituicao->loadMissing('planoAssinatura');
        $plano = $instituicao->planoAssinatura;

        $statusAtivo = in_array($instituicao->assinatura_status, [
            Instituicao::ASSINATURA_ACTIVE,
            Instituicao::ASSINATURA_PAST_DUE,
        ], true);

        if ($statusAtivo && $plano !== null) {
            return [
                'modulos' => $this->normalizeModulos($plano->modulos),
                'limite_alunos' => $plano->limite_alunos,
                'em_trial' => false,
            ];
        }

        return [
            'modulos' => [],
            'limite_alunos' => $plano?->limite_alunos,
            'em_trial' => false,
        ];
    }

    /**
     * Trial com data futura, ou trialing legado sem trial_ends_at (libera tudo).
     */
    private function isTrialUnlocked(Instituicao $instituicao): bool
    {
        if ($instituicao->assinatura_status !== Instituicao::ASSINATURA_TRIALING) {
            return false;
        }

        if ($instituicao->trial_ends_at === null) {
            return true;
        }

        return $instituicao->trial_ends_at->isFuture();
    }

    /**
     * @return list<string>
     */
    public function modulos(Instituicao $instituicao): array
    {
        return $this->resolve($instituicao)['modulos'];
    }

    public function limiteAlunos(Instituicao $instituicao): ?int
    {
        return $this->resolve($instituicao)['limite_alunos'];
    }

    public function permiteModulo(Instituicao $instituicao, string $modulo): bool
    {
        return in_array($modulo, $this->modulos($instituicao), true);
    }

    /**
     * @param  mixed  $modulos
     * @return list<string>
     */
    public function normalizeModulos(mixed $modulos): array
    {
        if (! is_array($modulos)) {
            return [];
        }

        $allowed = $this->catalogKeys();

        return array_values(array_unique(array_filter(
            array_map('strval', $modulos),
            fn (string $key) => in_array($key, $allowed, true),
        )));
    }
}

<?php

namespace App\Modules\Core\UseCases\Instituicao;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Validation\ValidationException;

class AlterarModuloInstituicaoUseCase
{
    public function __construct(
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    /**
     * Desligar só oculta e bloqueia o módulo; os dados dele permanecem intactos.
     */
    public function execute(Instituicao $instituicao, string $modulo, bool $ativo): Instituicao
    {
        if (! $this->entitlements->moduloContratado($instituicao, $modulo)) {
            throw ValidationException::withMessages([
                'modulo' => 'Este módulo não está incluído no plano da escola.',
            ]);
        }

        $desativados = $this->entitlements->normalizeModulos($instituicao->modulos_desativados);
        $desativados = $ativo
            ? array_values(array_diff($desativados, [$modulo]))
            : array_values(array_unique([...$desativados, $modulo]));

        $instituicao->update(['modulos_desativados' => $desativados === [] ? null : $desativados]);

        return $instituicao;
    }
}

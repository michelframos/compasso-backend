<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Validation\ValidationException;

class DeletePlatformPlanoAssinaturaUseCase
{
    public function __construct(
        private readonly PlanoAssinaturaRepositoryInterface $planos,
    ) {}

    public function execute(PlanoAssinatura $plano): void
    {
        if ($this->planos->countInstituicoesVinculadas($plano) > 0) {
            throw ValidationException::withMessages([
                'plano' => ['Não é possível excluir: existem escolas vinculadas a este plano.'],
            ]);
        }

        $this->planos->delete($plano);
    }
}

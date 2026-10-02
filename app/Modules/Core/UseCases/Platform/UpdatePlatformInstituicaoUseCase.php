<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\PlatformTrialResolver;

class UpdatePlatformInstituicaoUseCase
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
        private readonly PlatformTrialResolver $trialResolver,
    ) {}

    public function execute(Instituicao $instituicao, array $data): Instituicao
    {
        $data = $this->trialResolver->resolveForUpdate($instituicao, $data);

        return $this->instituicoes->update($instituicao, $data);
    }
}

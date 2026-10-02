<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\Instituicao;

class DeletePlatformInstituicaoUseCase
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
    ) {}

    public function execute(Instituicao $instituicao): void
    {
        $this->instituicoes->delete($instituicao);
    }
}

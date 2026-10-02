<?php

namespace App\Modules\Core\UseCases\ConfiguracaoEmpresa;

use App\Modules\Core\Models\ConfiguracaoEmpresa;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpsertConfiguracaoEmpresaUseCase
{
    public function execute(array $data): ConfiguracaoEmpresa
    {
        if (! InstituicaoContext::has()) {
            throw new ModelNotFoundException('Instituição não definida no contexto.');
        }

        $config = ConfiguracaoEmpresa::query()->first();

        if ($config === null) {
            throw new ModelNotFoundException('Instituição não encontrada.');
        }

        $config->update($data);

        return $config->load(['estado', 'cidade']);
    }
}

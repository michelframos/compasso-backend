<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\ConfiguracaoEmpresa\UpdateConfiguracaoEmpresaRequest;
use App\Modules\Core\Models\ConfiguracaoEmpresa;
use App\Modules\Core\UseCases\ConfiguracaoEmpresa\UpsertConfiguracaoEmpresaUseCase;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Core', description: 'Configuração dos dados da empresa')]
class ConfiguracaoEmpresaController extends Controller
{
    public function __construct(
        private readonly UpsertConfiguracaoEmpresaUseCase $upsertConfig,
    ) {}

    #[OA\Get(
        path: '/api/configuracao-empresa',
        summary: 'Obter configuração da empresa',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Configuração da empresa'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function show()
    {
        $config = ConfiguracaoEmpresa::with(['estado', 'cidade'])->first();

        return response()->json($config);
    }

    #[OA\Put(
        path: '/api/configuracao-empresa',
        summary: 'Salvar configuração da empresa',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Configuração salva'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateConfiguracaoEmpresaRequest $request)
    {
        $config = $this->upsertConfig->execute($request->validated());

        return response()->json($config);
    }
}

<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Exceptions\CepProviderException;
use App\Modules\Core\Http\Resources\EnderecoCepResource;
use App\Modules\Core\Models\Cidade;
use App\Modules\Core\Models\Estado;
use App\Modules\Core\UseCases\Localidade\BuscarEnderecoPorCepUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Core', description: 'Listagem de Estados e Cidades')]
class LocalidadeController extends Controller
{
    #[OA\Get(
        path: '/api/estados',
        summary: 'Listar todos os estados',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'Lista de estados')]
    )]
    public function estados()
    {
        return response()->json(Estado::orderBy('nome')->get());
    }

    #[OA\Get(
        path: '/api/estados/{estadoId}/cidades',
        summary: 'Listar cidades de um estado',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'Lista de cidades')]
    )]
    public function cidades($estadoId)
    {
        return response()->json(Cidade::where('id_estado', $estadoId)->orderBy('nome')->get());
    }

    #[OA\Get(
        path: '/api/cep/{cep}',
        summary: 'Buscar endereço pelo CEP',
        description: 'Consulta o provedor de CEP configurado (padrão: ViaCEP) e devolve o endereço com id_estado/id_cidade resolvidos pelo código IBGE. Resultados ficam em cache por 30 dias.',
        security: [['sanctum' => []]],
        tags: ['Core'],
        parameters: [
            new OA\Parameter(name: 'cep', in: 'path', required: true, description: '8 dígitos, com ou sem hífen', schema: new OA\Schema(type: 'string', example: '01001-000')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Endereço encontrado', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EnderecoCepResource')]
            )),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'CEP não encontrado ou em formato inválido'),
            new OA\Response(response: 429, description: 'Muitas consultas em pouco tempo'),
            new OA\Response(response: 503, description: 'Serviço de CEP indisponível'),
        ]
    )]
    public function cep(string $cep, BuscarEnderecoPorCepUseCase $buscarEndereco): EnderecoCepResource|JsonResponse
    {
        try {
            $resultado = $buscarEndereco->execute($cep);
        } catch (CepProviderException $e) {
            Log::warning('Falha na consulta de CEP', ['cep' => $cep, 'erro' => $e->getMessage()]);

            return response()->json([
                'message' => 'Serviço de CEP indisponível. Preencha o endereço manualmente.',
            ], 503);
        }

        if ($resultado === null) {
            return response()->json(['message' => 'CEP não encontrado.'], 404);
        }

        return new EnderecoCepResource($resultado);
    }
}

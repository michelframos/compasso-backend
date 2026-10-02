<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cidade;
use App\Modules\Core\Models\Estado;
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
}

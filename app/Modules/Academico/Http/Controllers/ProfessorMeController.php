<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Resources\ProfessorAutenticadoResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfessorMeController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me',
        summary: 'Dados do professor autenticado (área do professor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Professor autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ProfessorAutenticadoResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição ou precisa trocar a senha'),
        ]
    )]
    public function show(Request $request)
    {
        return new ProfessorAutenticadoResource($request->user()->professor->load('usuario'));
    }
}

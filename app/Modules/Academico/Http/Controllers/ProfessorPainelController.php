<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Resources\AulaDoProfessorResource;
use App\Modules\Academico\Services\PainelProfessorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfessorPainelController extends Controller
{
    public function __construct(private readonly PainelProfessorService $painel) {}

    #[OA\Get(
        path: '/api/professor/me/painel',
        summary: 'Painel inicial do professor: aulas de hoje e da semana, chamadas pendentes e contadores',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Painel do professor',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'data_referencia', type: 'string', format: 'date', example: '2026-10-01'),
                        new OA\Property(property: 'inicio_semana', type: 'string', format: 'date', example: '2026-09-28'),
                        new OA\Property(property: 'fim_semana', type: 'string', format: 'date', example: '2026-10-04'),
                        new OA\Property(property: 'contadores', type: 'object', properties: [
                            new OA\Property(property: 'aulas_hoje', type: 'integer', example: 2),
                            new OA\Property(property: 'aulas_semana', type: 'integer', example: 9),
                            new OA\Property(property: 'chamadas_pendentes', type: 'integer', example: 1),
                            new OA\Property(property: 'turmas_ativas', type: 'integer', example: 4),
                            new OA\Property(property: 'alunos_ativos', type: 'integer', example: 27),
                        ]),
                        new OA\Property(property: 'aulas_hoje', type: 'array', items: new OA\Items(ref: '#/components/schemas/AulaDoProfessorResource')),
                        new OA\Property(property: 'aulas_semana', type: 'array', items: new OA\Items(ref: '#/components/schemas/AulaDoProfessorResource')),
                        new OA\Property(
                            property: 'chamadas_pendentes',
                            type: 'array',
                            description: 'Até 20 aulas passadas (mais recentes primeiro) com status agendada ou sem presenças',
                            items: new OA\Items(ref: '#/components/schemas/AulaDoProfessorResource')
                        ),
                    ]),
                ])
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição ou precisa trocar a senha'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $painel = $this->painel->montar($request->user());

        return response()->json(['data' => [
            ...$painel,
            'aulas_hoje' => AulaDoProfessorResource::collection($painel['aulas_hoje']),
            'aulas_semana' => AulaDoProfessorResource::collection($painel['aulas_semana']),
            'chamadas_pendentes' => AulaDoProfessorResource::collection($painel['chamadas_pendentes']),
        ]]);
    }
}

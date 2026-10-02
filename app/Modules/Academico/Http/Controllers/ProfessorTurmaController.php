<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Turma\ListTurmasDoProfessorRequest;
use App\Modules\Academico\Http\Resources\TurmaDoProfessorResource;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListTurmasDoProfessorQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ProfessorTurmaController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/turmas',
        summary: 'Turmas do professor autenticado, com horários e total de alunos ativos',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'situacao', in: 'query', required: false, description: 'ativas (padrão), encerradas (concluídas/canceladas) ou todas', schema: new OA\Schema(type: 'string', enum: ['ativas', 'encerradas', 'todas'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Busca por descrição, curso ou nível', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Turmas do professor', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TurmaDoProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição ou precisa trocar a senha'),
            new OA\Response(response: 422, description: 'Filtro inválido'),
        ]
    )]
    public function index(ListTurmasDoProfessorRequest $request, ListTurmasDoProfessorQuery $query)
    {
        return TurmaDoProfessorResource::collection(
            $query->build($request->user(), $request->validated())->get()
        );
    }

    #[OA\Get(
        path: '/api/professor/me/turmas/{turma}',
        summary: 'Detalhe de uma turma do professor (horários e alunos matriculados)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'turma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Turma', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TurmaDoProfessorResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor'),
            new OA\Response(response: 404, description: 'Turma não encontrada'),
        ]
    )]
    public function show(Request $request, Turma $turma)
    {
        Gate::authorize('view', $turma);

        $turma->load([
            'curso',
            'nivel',
            'horarios' => fn ($q) => $q->orderBy('hora_inicio'),
            'matriculas' => fn ($q) => $q->whereNotIn('status', ['cancelada', 'transferida'])->with('aluno.usuario'),
        ])->loadCount(['matriculas as alunos_ativos_count' => fn ($q) => $q->where('status', 'ativa')]);

        return new TurmaDoProfessorResource($turma);
    }
}

<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Resources\AulaDoProfessorResource;
use App\Modules\Academico\Models\AulaTurma;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ProfessorAulaController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/aulas/{aulaTurma}',
        summary: 'Aula do professor para a chamada: alunos ativos da turma e presenças já lançadas',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'aulaTurma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aula', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AulaDoProfessorResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula que o professor não leciona'),
            new OA\Response(response: 404, description: 'Aula não encontrada'),
        ]
    )]
    public function show(AulaTurma $aulaTurma)
    {
        Gate::authorize('view', $aulaTurma);

        $aulaTurma->load([
            'turma.curso',
            'turma.nivel',
            'turma.matriculas' => fn ($q) => $q->where('status', 'ativa')->with('aluno.usuario'),
            'aluno_especifico.usuario',
            'presencas',
        ])->loadCount('presencas');

        // O campo "data" da aula impediria o JsonResource de aplicar o envelope "data".
        return response()->json(['data' => new AulaDoProfessorResource($aulaTurma)]);
    }
}

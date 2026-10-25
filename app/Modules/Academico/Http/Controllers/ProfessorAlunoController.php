<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Aluno\ListAlunosDoProfessorRequest;
use App\Modules\Academico\Http\Resources\AlunoDoProfessorResource;
use App\Modules\Academico\Http\Resources\FichaAlunoResource;
use App\Modules\Academico\Queries\ListAlunosDoProfessorQuery;
use App\Modules\Academico\Services\FichaAlunoService;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ProfessorAlunoController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/alunos',
        summary: 'Alunos do professor: matrícula vigente em suas turmas ou por curso (aulas individuais) atribuída a ele',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'query', required: false, description: 'turma ou curso; vazio traz ambos', schema: new OA\Schema(type: 'string', enum: ['turma', 'curso'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Busca por nome ou e-mail', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alunos do professor', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlunoDoProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição ou precisa trocar a senha'),
            new OA\Response(response: 422, description: 'Filtro inválido'),
        ]
    )]
    public function index(ListAlunosDoProfessorRequest $request, ListAlunosDoProfessorQuery $query)
    {
        return AlunoDoProfessorResource::collection(
            $query->build($request->user(), $request->validated())->get()
        );
    }

    #[OA\Get(
        path: '/api/professor/me/alunos/{aluno}',
        summary: 'Ficha do aluno: dados básicos, matrículas nas turmas do professor, frequência e observações pedagógicas',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'aluno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ficha do aluno', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/FichaAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aluno sem matrícula vigente nas turmas do professor'),
            new OA\Response(response: 404, description: 'Aluno não encontrado'),
        ]
    )]
    public function show(Request $request, Aluno $aluno, FichaAlunoService $ficha)
    {
        Gate::authorize('verFichaAluno', $aluno);

        return new FichaAlunoResource($ficha->montar($aluno, $request->user()));
    }
}

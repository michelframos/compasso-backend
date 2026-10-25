<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Aula\ConcluirAulaRequest;
use App\Modules\Academico\Http\Resources\AulaDoProfessorResource;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\UseCases\Aula\ConcluirAulaUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ConcluirAulaController extends Controller
{
    #[OA\Post(
        path: '/api/aulas-turmas/{aulaTurma}/concluir',
        summary: 'Concluir aula: registra a chamada, o conteúdo ministrado e congela os valores da aula',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'aulaTurma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConcluirAulaRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Aula concluída', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Aula concluída com sucesso.'),
                new OA\Property(property: 'data', ref: '#/components/schemas/AulaDoProfessorResource'),
            ])),
            new OA\Response(response: 403, description: 'Professor não leciona a aula, aula já concluída (somente admin altera) ou turma encerrada'),
            new OA\Response(response: 422, description: 'Aula cancelada, futura ou com alunos que não pertencem a ela'),
        ]
    )]
    public function store(ConcluirAulaRequest $request, AulaTurma $aulaTurma, ConcluirAulaUseCase $concluirAula)
    {
        Gate::authorize('registrarPresencas', $aulaTurma);

        if ($aulaTurma->status === 'concluida') {
            Gate::authorize('alterarConcluida', $aulaTurma);
        }

        $aula = $concluirAula->execute($aulaTurma, $request->validated())
            ->load(['turma.curso', 'turma.nivel', 'aluno_especifico.usuario', 'presencas'])
            ->loadCount('presencas');

        return response()->json([
            'message' => 'Aula concluída com sucesso.',
            'data' => new AulaDoProfessorResource($aula),
        ]);
    }
}

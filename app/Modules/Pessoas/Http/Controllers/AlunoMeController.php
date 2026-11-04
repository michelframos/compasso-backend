<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Pessoas\Http\Resources\AlunoAutenticadoResource;
use App\Modules\Pessoas\Http\Resources\DependenteResource;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AlunoMeController extends Controller
{
    public function __construct(
        private readonly AlunoAlvoResolverPort $alunos,
    ) {}

    #[OA\Get(
        path: '/api/aluno/me',
        summary: 'Aluno autenticado ou dependente escolhido pelo responsável',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aluno da área', content: new OA\JsonContent(ref: '#/components/schemas/AlunoAutenticadoResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição, sem vínculo ou senha pendente'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function show(Request $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);

        return new AlunoAutenticadoResource($aluno->load('usuario'));
    }

    #[OA\Get(
        path: '/api/aluno/me/dependentes',
        summary: 'Dependentes vinculados ao responsável autenticado',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        responses: [
            new OA\Response(response: 200, description: 'Dependentes, em ordem de nome', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DependenteResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido ou aluno sem perfil de responsável'),
        ]
    )]
    public function dependentes(Request $request)
    {
        Gate::authorize('listarDependentes', Responsavel::class);

        $dependentes = $request->user()->responsavel
            ->alunos()
            ->with('usuario')
            ->get()
            ->sortBy(fn ($aluno) => $aluno->usuario?->nome)
            ->values();

        return DependenteResource::collection($dependentes);
    }
}

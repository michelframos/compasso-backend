<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Pessoas\Http\Requests\MedidaAluno\ListMedidasDoAlunoRequest;
use App\Modules\Pessoas\Http\Requests\MedidaAluno\UpdateMedidaAlunoPeloAlunoRequest;
use App\Modules\Pessoas\Http\Resources\MedidaDoAlunoResource;
use App\Modules\Pessoas\Models\MedidaAluno;
use App\Modules\Pessoas\Support\EdicaoMedidasDoAluno;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoMedidaController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/medidas',
        summary: 'Medidas de figurino do aluno',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Medidas, da mais recente à mais antiga. pode_editar indica a flag da escola.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/MedidaDoAlunoResource')),
                new OA\Property(property: 'pode_editar', type: 'boolean'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo ou outra instituição'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListMedidasDoAlunoRequest $request)
    {
        $aluno = $this->alunoAlvo($request);

        Gate::authorize('viewAnyDoAluno', [MedidaAluno::class, $aluno->id]);

        $medidas = MedidaAluno::query()
            ->where('id_aluno', $aluno->id)
            ->orderByDesc('id')
            ->get();

        return MedidaDoAlunoResource::collection($medidas)->additional([
            'pode_editar' => EdicaoMedidasDoAluno::permitida(),
        ]);
    }

    #[OA\Put(
        path: '/api/aluno/me/medidas/{medida}',
        summary: 'Corrigir as medidas quando a escola permite',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        parameters: [
            new OA\Parameter(name: 'medida', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateMedidaAlunoPeloAlunoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Medida atualizada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/MedidaDoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem vínculo, medida de outro aluno ou escola sem a permissão'),
            new OA\Response(response: 404, description: 'Medida não encontrada'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno ou medida inválida'),
        ]
    )]
    public function update(UpdateMedidaAlunoPeloAlunoRequest $request, MedidaAluno $medida)
    {
        $aluno = $this->alunoAlvo($request);

        if ((int) $medida->id_aluno !== (int) $aluno->id) {
            throw new HttpException(403, 'Acesso restrito: esta medida não é sua.');
        }

        Gate::authorize('update', $medida);

        $medida->update($request->safe()->except('id_aluno'));

        return new MedidaDoAlunoResource($medida);
    }

    private function alunoAlvo(ListMedidasDoAlunoRequest|UpdateMedidaAlunoPeloAlunoRequest $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idAluno);
    }
}

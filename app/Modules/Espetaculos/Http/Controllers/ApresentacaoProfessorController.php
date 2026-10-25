<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Espetaculos\Http\Requests\Apresentacao\ListApresentacoesProfessorRequest;
use App\Modules\Espetaculos\Http\Resources\ApresentacaoDoProfessorResource;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ApresentacaoProfessorController extends Controller
{
    private const RELACOES = ['espetaculo', 'turma.curso', 'turma.nivel'];

    #[OA\Get(
        path: '/api/professor/me/apresentacoes',
        summary: 'Minhas apresentações: das turmas do professor ou com participação de alunos seus',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'situacao', in: 'query', required: false, description: 'proximas (padrão: espetáculo de hoje em diante), passadas ou todas', schema: new OA\Schema(type: 'string', enum: ListApresentacoesProfessorRequest::SITUACOES)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Apresentações por data do espetáculo e ordem de entrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApresentacaoDoProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não é professor ou módulo de espetáculos indisponível'),
        ]
    )]
    public function index(ListApresentacoesProfessorRequest $request)
    {
        $situacao = $request->validated('situacao') ?? 'proximas';
        $hoje = now()->toDateString();
        $dataDoEspetaculo = Espetaculo::query()->select('data_evento')->whereColumn('espetaculos.id', 'apresentacoes.id_espetaculo');

        $apresentacoes = $this->consulta($request)
            ->when($situacao === 'proximas', fn (Builder $q) => $q->whereHas('espetaculo', fn (Builder $e) => $e->where('data_evento', '>=', $hoje)))
            ->when($situacao === 'passadas', fn (Builder $q) => $q->whereHas('espetaculo', fn (Builder $e) => $e->where('data_evento', '<', $hoje)))
            ->with(['ensaios' => fn ($q) => $q->where('data', '>=', $hoje)->orderBy('data')->orderBy('hora_inicio')])
            ->orderBy($dataDoEspetaculo, $situacao === 'passadas' ? 'desc' : 'asc')
            ->orderBy('ordem_entrada')
            ->get();

        return ApresentacaoDoProfessorResource::collection($apresentacoes);
    }

    #[OA\Get(
        path: '/api/professor/me/apresentacoes/{apresentacao}',
        summary: 'Detalhe da apresentação para o professor: participantes (sem valores de figurino) e ensaios',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'apresentacao', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Apresentação', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ApresentacaoDoProfessorResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apresentação sem turma ou alunos do professor'),
            new OA\Response(response: 404, description: 'Apresentação não encontrada'),
        ]
    )]
    public function show(Request $request, Apresentacao $apresentacao)
    {
        Gate::authorize('view', $apresentacao);

        $detalhe = $this->consulta($request)
            ->whereKey($apresentacao->id)
            ->with([
                'alunos.aluno.usuario',
                'ensaios' => fn ($q) => $q->with('professor.usuario')->orderBy('data')->orderBy('hora_inicio'),
            ])
            ->firstOrFail();

        $idsMeusAlunos = Apresentacao::alunosDoProfessor($request->user())->pluck('id_aluno');

        return (new ApresentacaoDoProfessorResource($detalhe))->comDetalhes($idsMeusAlunos);
    }

    private function consulta(Request $request): Builder
    {
        $user = $request->user();

        return Apresentacao::query()
            ->visivelPara($user)
            ->with(self::RELACOES)
            ->withCount([
                'alunos as total_participantes',
                'alunos as meus_alunos_count' => fn (Builder $q) => $q->whereIn('id_aluno', Apresentacao::alunosDoProfessor($user)),
            ]);
    }
}

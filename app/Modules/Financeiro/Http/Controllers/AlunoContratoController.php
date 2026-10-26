<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoEmpresa;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Financeiro\Http\Requests\AlunoContrato\ConsultarContratoDoAlunoRequest;
use App\Modules\Financeiro\Http\Resources\ContratoDoAlunoResource;
use App\Modules\Financeiro\Models\AlunoContrato;
use App\Modules\Financeiro\UseCases\AlunoContrato\AceitarContratoUseCase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoContratoController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/contratos',
        summary: 'Contratos gerados para o aluno',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contratos, do mais recente ao mais antigo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ContratoDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo ou outra instituição'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ConsultarContratoDoAlunoRequest $request)
    {
        $aluno = $this->alunoAlvo($request);
        Gate::authorize('viewAnyDoAluno', [AlunoContrato::class, $aluno->id]);

        $contratos = AlunoContrato::query()
            ->with('contrato')
            ->where('aluno_id', $aluno->id)
            ->orderByDesc('id')
            ->get();

        return ContratoDoAlunoResource::collection($contratos);
    }

    #[OA\Get(
        path: '/api/aluno/me/contratos/{alunoContrato}/pdf',
        summary: 'PDF do contrato gerado para o aluno',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'alunoContrato', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF do contrato'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Contrato de outro aluno'),
            new OA\Response(response: 404, description: 'Contrato não encontrado'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function pdf(ConsultarContratoDoAlunoRequest $request, AlunoContrato $alunoContrato)
    {
        $this->garantir($request, $alunoContrato);
        $alunoContrato->loadMissing('contrato', 'aluno.usuario');

        $aceitoEm = $alunoContrato->aceito_em
            ? substr((string) $alunoContrato->aceito_em, 0, 16)
            : null;

        $pdf = Pdf::loadView('pdf.contrato', [
            'escola' => ConfiguracaoEmpresa::query()->value('nome_fantasia') ?? 'Escola',
            'nome' => $alunoContrato->contrato?->nome ?? 'Contrato',
            'aluno' => $alunoContrato->aluno?->usuario?->nome ?? 'Aluno',
            'conteudo' => $alunoContrato->contrato_gerado ?? '',
            'aceito_em' => $aceitoEm,
            'emitido_em' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('contrato_'.$alunoContrato->id.'.pdf');
    }

    #[OA\Post(
        path: '/api/aluno/me/contratos/{alunoContrato}/aceite',
        summary: 'Aceite digital do contrato pelo aluno ou responsável',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'alunoContrato', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contrato aceito', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ContratoDoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Contrato de outro aluno'),
            new OA\Response(response: 404, description: 'Contrato não encontrado'),
            new OA\Response(response: 422, description: 'Já aceito, sem conteúdo ou responsável sem id_aluno'),
        ]
    )]
    public function aceite(ConsultarContratoDoAlunoRequest $request, AlunoContrato $alunoContrato, AceitarContratoUseCase $aceitar)
    {
        $this->garantir($request, $alunoContrato, 'aceitar');

        $aceito = $aceitar->execute(
            $alunoContrato,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return new ContratoDoAlunoResource($aceito);
    }

    private function garantir(ConsultarContratoDoAlunoRequest $request, AlunoContrato $contrato, string $habilidade = 'view'): void
    {
        $aluno = $this->alunoAlvo($request);

        if ((int) $contrato->aluno_id !== (int) $aluno->id) {
            throw new HttpException(403, 'Acesso restrito: este contrato não é seu.');
        }

        Gate::authorize($habilidade, $contrato);
    }

    private function alunoAlvo(ConsultarContratoDoAlunoRequest $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idAluno);
    }
}

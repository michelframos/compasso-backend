<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoEmpresa;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Financeiro\Http\Requests\Conta\GerarCarneAlunoRequest;
use App\Modules\Financeiro\Http\Requests\Conta\ListContasDoAlunoRequest;
use App\Modules\Financeiro\Http\Resources\ContaDoAlunoResource;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Financeiro\Queries\ListContasDoAlunoQuery;
use App\Modules\Financeiro\Services\GerarCarneService;
use App\Modules\Financeiro\Services\PixPayloadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoFinanceiroController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/contas',
        summary: 'Mensalidades do aluno, por situação',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'situacao', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['em_aberto', 'pagas', 'atrasadas'])),
            new OA\Parameter(name: 'id_matricula', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contas de receita do aluno, da mais próxima a vencer', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ContaDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, módulo ausente, sem vínculo ou matrícula de outro aluno'),
            new OA\Response(response: 404, description: 'Matrícula não encontrada'),
            new OA\Response(response: 422, description: 'Filtro inválido ou responsável sem id_aluno'),
        ]
    )]
    public function contas(ListContasDoAlunoRequest $request, ListContasDoAlunoQuery $query)
    {
        $aluno = $this->resolverAluno($request);
        $idMatricula = $request->filled('id_matricula') ? $request->integer('id_matricula') : null;

        if ($idMatricula !== null) {
            $matricula = Matricula::query()->find($idMatricula);
            if ($matricula === null) {
                abort(404);
            }
            if ($matricula->id_aluno !== $aluno->id) {
                throw new HttpException(403, 'Acesso restrito: esta matrícula não é sua.');
            }
        }

        return ContaDoAlunoResource::collection(
            $query->build($request->user(), $aluno->id, $request->validated('situacao'), $idMatricula)->get()
        );
    }

    #[OA\Get(
        path: '/api/aluno/me/contas/consolidado',
        summary: 'Mensalidades de todos os dependentes do responsável',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'situacao', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['em_aberto', 'pagas', 'atrasadas'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contas de todos os dependentes, da mais próxima a vencer', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ContaDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido ou módulo financeiro ausente'),
            new OA\Response(response: 422, description: 'Situação inválida'),
        ]
    )]
    public function consolidado(ListContasDoAlunoRequest $request, ListContasDoAlunoQuery $query)
    {
        Gate::authorize('viewConsolidado', Conta::class);

        return ContaDoAlunoResource::collection(
            $query->buildDoResponsavel($request->user(), $request->validated('situacao'))->get()
        );
    }

    #[OA\Get(
        path: '/api/aluno/me/contas/{conta}/pix',
        summary: 'PIX da conta: copia-e-cola e QR Code',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'conta', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payload e QR em base64', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'payload', type: 'string'),
                    new OA\Property(property: 'qr_code', type: 'string', description: 'PNG em base64'),
                    new OA\Property(property: 'valor', type: 'number', format: 'float'),
                    new OA\Property(property: 'descricao', type: 'string'),
                ]),
            ])),
            new OA\Response(response: 400, description: 'Escola sem chave PIX'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Conta de outro aluno'),
            new OA\Response(response: 404, description: 'Conta não encontrada'),
            new OA\Response(response: 422, description: 'Conta já paga, cancelada ou responsável sem id_aluno'),
        ]
    )]
    public function pix(Request $request, Conta $conta, PixPayloadService $pix)
    {
        $aluno = $this->resolverAluno($request);
        $this->garantirConta($conta, $aluno->id);

        if (in_array($conta->status, ['pago', 'cancelado'], true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Esta conta não está em aberto.',
            ], 422));
        }

        $config = ConfiguracaoPix::query()->first();
        if ($config === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Configuração PIX não cadastrada. Acesse as configurações do sistema para cadastrar a chave PIX da escola.',
            ], 400));
        }

        $conta->loadMissing('pagamentos');
        $gerado = $pix->paraConta($config, $conta);

        return response()->json([
            'data' => [
                'payload' => $gerado['payload'],
                'qr_code' => $gerado['qr_code'],
                'valor' => $gerado['valor'],
                'descricao' => $conta->descricao,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/aluno/me/carne',
        summary: 'Carnê em PDF das contas do aluno',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['conta_ids'], properties: [
            new OA\Property(property: 'conta_ids', type: 'array', items: new OA\Items(type: 'integer')),
            new OA\Property(property: 'titulo', type: 'string', nullable: true),
            new OA\Property(property: 'id_aluno', type: 'integer', nullable: true, description: 'Obrigatório para o responsável'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'PDF do carnê'),
            new OA\Response(response: 400, description: 'Escola sem chave PIX'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Alguma conta não é do aluno'),
            new OA\Response(response: 422, description: 'Lista inválida ou responsável sem id_aluno'),
        ]
    )]
    public function carne(GerarCarneAlunoRequest $request, GerarCarneService $carne)
    {
        $aluno = $this->resolverAluno($request);
        $ids = array_values(array_unique($request->validated('conta_ids')));

        $contas = Conta::query()->whereIn('id', $ids)->get();
        if ($contas->count() !== count($ids)) {
            abort(404);
        }

        foreach ($contas as $conta) {
            $this->garantirConta($conta, $aluno->id);
        }

        $preferencia = $request->user()->role === 'responsavel' ? 'responsavel' : 'aluno';

        return $carne->download($contas, $request->validated('titulo'), $preferencia);
    }

    #[OA\Get(
        path: '/api/aluno/me/pagamentos/{pagamento}/recibo',
        summary: 'Recibo em PDF de um pagamento do aluno',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'pagamento', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF do recibo'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Pagamento de outro aluno'),
            new OA\Response(response: 404, description: 'Pagamento não encontrado'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function recibo(Request $request, ContaPagamento $pagamento)
    {
        $aluno = $this->resolverAluno($request);
        $pagamento->loadMissing('conta.aluno.usuario');
        $conta = $pagamento->conta;

        if ($conta === null) {
            abort(404);
        }

        $this->garantirConta($conta, $aluno->id);

        $escola = ConfiguracaoEmpresa::query()->value('nome_fantasia')
            ?? ConfiguracaoPix::query()->value('nome_beneficiario')
            ?? 'Escola';

        $pdf = Pdf::loadView('pdf.recibo', [
            'pagamento' => $pagamento,
            'conta' => $conta,
            'aluno' => $conta->aluno?->usuario?->nome ?? 'Aluno',
            'escola' => $escola,
            'emitido_em' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('recibo_'.$pagamento->id.'.pdf');
    }

    private function resolverAluno(Request $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);
        Gate::authorize('viewAnyDoAluno', [Conta::class, $aluno->id]);

        return $aluno;
    }

    private function garantirConta(Conta $conta, int $idAluno): void
    {
        if ($conta->id_aluno !== $idAluno || $conta->tipo !== 'receita') {
            throw new HttpException(403, 'Acesso restrito: esta conta não é sua.');
        }

        Gate::authorize('view', $conta);
    }
}

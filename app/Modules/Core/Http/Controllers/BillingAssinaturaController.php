<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Http\Concerns\RespondsBillingGatewayErrors;
use App\Modules\Core\Http\Requests\Assinatura\CheckoutAssinaturaRequest;
use App\Modules\Core\Http\Requests\Assinatura\UpdateDestinoSaqueRequest;
use App\Modules\Core\Http\Resources\AssinaturaCobrancaResource;
use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\UseCases\Assinatura\AtualizarDestinoSaqueSubcontaUseCase;
use App\Modules\Core\UseCases\Assinatura\CancelarAssinaturaOnlineUseCase;
use App\Modules\Core\UseCases\Assinatura\CheckoutAssinaturaUseCase;
use App\Modules\Core\UseCases\Assinatura\ProvisionarSubcontaEscolaUseCase;
use App\Modules\Core\UseCases\Assinatura\SyncAssinaturaCobrancaUseCase;
use App\Modules\Core\UseCases\Assinatura\SyncSubcontaEscolaUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class BillingAssinaturaController extends Controller
{
    use RespondsBillingGatewayErrors;

    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly CheckoutAssinaturaUseCase $checkoutUseCase,
        private readonly SyncAssinaturaCobrancaUseCase $syncAssinatura,
        private readonly CancelarAssinaturaOnlineUseCase $cancelarUseCase,
        private readonly ProvisionarSubcontaEscolaUseCase $provisionarSubcontaUseCase,
        private readonly SyncSubcontaEscolaUseCase $syncSubconta,
        private readonly AtualizarDestinoSaqueSubcontaUseCase $atualizarSaqueUseCase,
    ) {}

    #[OA\Get(
        path: '/api/assinatura/atual',
        summary: 'Assinatura atual da escola (plano, status, trial, cobrança aberta e subconta)',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function atual(): JsonResponse
    {
        $instituicao = $this->instituicaoAtual()->load('planoAssinatura');

        $cobranca = AssinaturaCobranca::query()
            ->with('plano')
            ->where('id_instituicao', $instituicao->id)
            ->naoFinais()
            ->latest('id')
            ->first()
            ?? AssinaturaCobranca::query()
                ->with('plano')
                ->where('id_instituicao', $instituicao->id)
                ->latest('id')
                ->first();

        $plano = $instituicao->planoAssinatura;

        return response()->json(['data' => [
            'billing_disponivel' => $this->gateway->isAvailable(),
            'assinatura_status' => $instituicao->assinatura_status,
            'assinatura_inicia_em' => $instituicao->assinatura_inicia_em?->toDateString(),
            'trial_ends_at' => $instituicao->trial_ends_at?->toIso8601String(),
            'plano' => $plano === null ? null : [
                'id' => $plano->id,
                'slug' => $plano->slug,
                'nome' => $plano->nome,
                'preco' => (float) $plano->preco_mensal,
                'limite_alunos' => $plano->limite_alunos,
            ],
            'cobranca' => $cobranca === null ? null : (new AssinaturaCobrancaResource($cobranca))->resolve(),
            'invoice_url' => $cobranca?->invoice_url,
            'subconta' => $this->subcontaPayload($instituicao),
        ]]);
    }

    #[OA\Post(
        path: '/api/assinatura/checkout',
        summary: 'Inicia a assinatura online (cria a assinatura no centralizador)',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CheckoutAssinaturaRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Checkout criado', content: new OA\JsonContent(ref: '#/components/schemas/AssinaturaCobrancaResource')),
            new OA\Response(response: 422, description: 'Validação / billing indisponível'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function checkout(CheckoutAssinaturaRequest $request): JsonResponse
    {
        try {
            $assinatura = $this->checkoutUseCase->execute($request->user(), $request->validated());
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return (new AssinaturaCobrancaResource($assinatura))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/assinatura/checkout/{id}',
        summary: 'Consulta o checkout (status local; use ?sync=1 para sincronizar com o centralizador)',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AssinaturaCobrancaResource')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function showCheckout(Request $request, AssinaturaCobranca $assinaturaCobranca): AssinaturaCobrancaResource
    {
        abort_unless($assinaturaCobranca->id_instituicao === InstituicaoContext::id(), 404);

        if ($request->boolean('sync') && ! $assinaturaCobranca->isFinal() && $this->gateway->isAvailable()) {
            try {
                $assinaturaCobranca = $this->syncAssinatura->execute($assinaturaCobranca);
            } catch (BillingGatewayException) {
                // Mantém o estado local; o job agendado tentará novamente.
            }
        }

        return new AssinaturaCobrancaResource($assinaturaCobranca->loadMissing('plano'));
    }

    #[OA\Post(
        path: '/api/assinatura/cancelar',
        summary: 'Cancela a assinatura online da escola',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [
            new OA\Response(response: 200, description: 'Cancelada', content: new OA\JsonContent(ref: '#/components/schemas/AssinaturaCobrancaResource')),
            new OA\Response(response: 422, description: 'Nenhuma assinatura para cancelar'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function cancelar(): JsonResponse|AssinaturaCobrancaResource
    {
        try {
            $assinatura = $this->cancelarUseCase->execute($this->instituicaoAtual());
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return new AssinaturaCobrancaResource($assinatura);
    }

    #[OA\Get(
        path: '/api/assinatura/subconta',
        summary: 'Status da subconta de recebimentos da escola',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function subconta(): JsonResponse
    {
        $instituicao = $this->instituicaoAtual();

        if (
            $instituicao->gateway_subaccount_status === Instituicao::SUBCONTA_PENDING
            && filled($instituicao->gateway_subaccount_uuid)
            && $this->gateway->isAvailable()
        ) {
            try {
                $instituicao = $this->syncSubconta->execute($instituicao);
            } catch (BillingGatewayException) {
                // Mantém o status local.
            }
        }

        return response()->json(['data' => $this->subcontaPayload($instituicao)]);
    }

    #[OA\Post(
        path: '/api/assinatura/subconta',
        summary: 'Cria ou retenta a subconta da escola (trial, active ou past_due)',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [
            new OA\Response(response: 200, description: 'Subconta solicitada'),
            new OA\Response(response: 422, description: 'Assinatura cancelada / dados incompletos'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function provisionarSubconta(Request $request): JsonResponse
    {
        $instituicao = $this->instituicaoAtual();

        if (! in_array($instituicao->assinatura_status, [
            Instituicao::ASSINATURA_TRIALING,
            Instituicao::ASSINATURA_ACTIVE,
            Instituicao::ASSINATURA_PAST_DUE,
        ], true)) {
            throw ValidationException::withMessages([
                'assinatura' => ['A subconta só pode ser criada durante o trial ou com a assinatura ativa/em atraso.'],
            ]);
        }

        try {
            $instituicao = $this->provisionarSubcontaUseCase->execute($instituicao, $request->user());
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return response()->json(['data' => $this->subcontaPayload($instituicao)]);
    }

    #[OA\Put(
        path: '/api/assinatura/subconta/saque',
        summary: 'Cadastra o destino de saque (PIX) da subconta',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDestinoSaqueRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Destino atualizado'),
            new OA\Response(response: 422, description: 'Validação / subconta inativa'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function atualizarSaque(UpdateDestinoSaqueRequest $request): JsonResponse
    {
        try {
            $this->atualizarSaqueUseCase->execute($this->instituicaoAtual(), $request->validated());
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return response()->json([
            'message' => 'Destino de saque atualizado.',
            'data' => ['pix_key_type' => $request->validated('pix_key_type')],
        ]);
    }

    private function instituicaoAtual(): Instituicao
    {
        return Instituicao::query()->findOrFail(InstituicaoContext::id());
    }

    /**
     * @return array<string, mixed>
     */
    private function subcontaPayload(Instituicao $instituicao): array
    {
        return [
            'status' => $instituicao->gateway_subaccount_status ?? Instituicao::SUBCONTA_NONE,
            'ativa' => $instituicao->subcontaAtiva(),
            'last_error' => $instituicao->gateway_subaccount_last_error,
            'meios_cobranca_online' => $instituicao->meiosCobrancaOnline(),
        ];
    }
}

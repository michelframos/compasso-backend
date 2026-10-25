<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Http\Requests\Instituicao\UpdateModuloInstituicaoRequest;
use App\Modules\Core\Http\Resources\ModuloInstituicaoResource;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\UseCases\Instituicao\AlterarModuloInstituicaoUseCase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ModuloInstituicaoController extends Controller
{
    public function __construct(
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    #[OA\Get(
        path: '/api/instituicao/modulos',
        summary: 'Lista os módulos do catálogo com a situação na escola (contratado, ativo e se pode ser desligado)',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        parameters: [new OA\Parameter(name: 'X-Tenant-Slug', in: 'header', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ModuloInstituicaoResource')),
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'modulos_ativos', type: 'array', items: new OA\Items(type: 'string')),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Somente o admin da escola'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return $this->listar($this->instituicao());
    }

    #[OA\Put(
        path: '/api/instituicao/modulos/{modulo}',
        summary: 'Liga ou desliga um módulo opcional na escola (desligar só oculta; nenhum dado é apagado)',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        parameters: [
            new OA\Parameter(name: 'modulo', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'progressao')),
            new OA\Parameter(name: 'X-Tenant-Slug', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateModuloInstituicaoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Lista atualizada (mesmo formato do GET)'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Somente o admin da escola'),
            new OA\Response(response: 422, description: 'Módulo não desligável ou fora do plano'),
        ]
    )]
    public function update(UpdateModuloInstituicaoRequest $request, AlterarModuloInstituicaoUseCase $alterar): AnonymousResourceCollection
    {
        $instituicao = $alterar->execute(
            $this->instituicao(),
            $request->validated('modulo'),
            $request->boolean('ativo'),
        );

        return $this->listar($instituicao);
    }

    private function instituicao(): Instituicao
    {
        return Instituicao::query()->findOrFail(InstituicaoContext::id());
    }

    private function listar(Instituicao $instituicao): AnonymousResourceCollection
    {
        $direitos = $this->entitlements->resolve($instituicao);
        $desativaveis = $this->entitlements->modulosDesativaveis();

        $itens = collect($this->entitlements->catalog())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'descricao' => $meta['descricao'] ?? '',
                'contratado' => in_array($key, $direitos['modulos_contratados'], true),
                'ativo' => in_array($key, $direitos['modulos'], true),
                'desativavel' => in_array($key, $desativaveis, true),
            ])
            ->values();

        return ModuloInstituicaoResource::collection($itens)
            ->additional(['meta' => ['modulos_ativos' => $direitos['modulos']]]);
    }
}

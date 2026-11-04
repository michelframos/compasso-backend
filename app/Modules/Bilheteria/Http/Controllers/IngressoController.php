<?php

namespace App\Modules\Bilheteria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bilheteria\Http\Requests\GerarIngressosRequest;
use App\Modules\Bilheteria\Http\Requests\ValidarUtilizarIngressoRequest;
use App\Modules\Bilheteria\Http\Requests\VenderIngressoRequest;
use App\Modules\Bilheteria\Http\Resources\IngressoResource;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\UseCases\CancelarVendaIngressoUseCase;
use App\Modules\Bilheteria\UseCases\GerarIngressosUseCase;
use App\Modules\Bilheteria\UseCases\MarcarCortesiaUseCase;
use App\Modules\Bilheteria\UseCases\UtilizarIngressoUseCase;
use App\Modules\Bilheteria\UseCases\ValidarIngressoUseCase;
use App\Modules\Bilheteria\UseCases\VenderIngressoUseCase;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IngressoController extends Controller
{
    use ResolvesSorting;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ingresso::class);

        $query = Ingresso::query()->with(['espetaculo']);

        if ($request->filled('id_espetaculo')) {
            $query->where('id_espetaculo', (int) $request->input('id_espetaculo'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('status_pagamento')) {
            $query->where('status_pagamento', $request->input('status_pagamento'));
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('comprador_nome', 'like', "%{$search}%")
                    ->orWhere('codigo_qr', 'like', "%{$search}%");
            });
        }

        $ingressos = $this->applySorting(
            $query,
            ['id', 'numero', 'valor', 'status', 'status_pagamento', 'created_at'],
            'numero'
        )->paginate((int) $request->input('per_page', 50));

        return IngressoResource::collection($ingressos);
    }

    public function show(Ingresso $ingresso)
    {
        Gate::authorize('view', $ingresso);

        return new IngressoResource($ingresso->load('espetaculo'));
    }

    public function gerar(GerarIngressosRequest $request, Espetaculo $espetaculo, GerarIngressosUseCase $useCase)
    {
        Gate::authorize('create', Ingresso::class);

        $dados = $request->validated();
        $criados = $useCase->execute(
            $espetaculo,
            (int) $dados['quantidade'],
            isset($dados['valor']) ? (float) $dados['valor'] : null,
            isset($dados['id_template_ingresso']) ? (int) $dados['id_template_ingresso'] : null,
        );

        return IngressoResource::collection(collect($criados))->response()->setStatusCode(201);
    }

    public function vender(VenderIngressoRequest $request, Ingresso $ingresso, VenderIngressoUseCase $useCase)
    {
        Gate::authorize('update', $ingresso);

        return new IngressoResource($useCase->execute($ingresso, $request->validated()));
    }

    public function cortesia(Request $request, Ingresso $ingresso, MarcarCortesiaUseCase $useCase)
    {
        Gate::authorize('update', $ingresso);

        $dados = $request->validate([
            'comprador_nome' => ['nullable', 'string', 'max:255'],
            'comprador_documento' => ['nullable', 'string', 'max:30'],
        ]);

        return new IngressoResource($useCase->execute($ingresso, $dados));
    }

    public function cancelarVenda(Ingresso $ingresso, CancelarVendaIngressoUseCase $useCase)
    {
        Gate::authorize('update', $ingresso);

        return new IngressoResource($useCase->execute($ingresso));
    }

    public function validar(ValidarUtilizarIngressoRequest $request, ValidarIngressoUseCase $useCase)
    {
        Gate::authorize('viewAny', Ingresso::class);

        $dados = $request->validated();
        $resultado = $useCase->execute($dados['numero'] ?? null, $dados['codigo_qr'] ?? null);

        return response()->json([
            'ok' => $resultado['ok'],
            'motivo' => $resultado['motivo'] ?? null,
            'data' => isset($resultado['ingresso'])
                ? new IngressoResource($resultado['ingresso'])
                : null,
        ], $resultado['ok'] ? 200 : 422);
    }

    public function utilizar(ValidarUtilizarIngressoRequest $request, UtilizarIngressoUseCase $useCase)
    {
        Gate::authorize('viewAny', Ingresso::class);

        $dados = $request->validated();
        $resultado = $useCase->execute($dados['numero'] ?? null, $dados['codigo_qr'] ?? null);

        return response()->json([
            'ok' => $resultado['ok'],
            'motivo' => $resultado['motivo'] ?? null,
            'message' => $resultado['ok']
                ? 'Ingresso utilizado com sucesso.'
                : ($resultado['motivo'] ?? 'Falha ao utilizar ingresso.'),
            'data' => isset($resultado['ingresso'])
                ? new IngressoResource($resultado['ingresso'])
                : null,
        ], $resultado['ok'] ? 200 : 422);
    }

    public function relatorio(Request $request)
    {
        Gate::authorize('viewAny', Ingresso::class);

        $request->validate([
            'id_espetaculo' => ['required', 'integer'],
        ]);

        $idEspetaculo = (int) $request->input('id_espetaculo');
        $base = Ingresso::query()->where('id_espetaculo', $idEspetaculo);

        $gerados = (clone $base)->count();
        $disponiveis = (clone $base)->where('status', Ingresso::STATUS_DISPONIVEL)->count();
        $vendidos = (clone $base)->where('status', Ingresso::STATUS_VENDIDO)->where('cortesia', false)->count();
        $cortesias = (clone $base)->where('cortesia', true)->count();
        $utilizados = (clone $base)->where('status', Ingresso::STATUS_UTILIZADO)->count();
        $receitaBruta = (clone $base)
            ->where('status_pagamento', Ingresso::PAGAMENTO_PAGO)
            ->where('cortesia', false)
            ->sum('valor');
        $cuponsUsados = (clone $base)->whereNotNull('id_cupom')->count();

        return response()->json([
            'data' => [
                'id_espetaculo' => $idEspetaculo,
                'gerados' => $gerados,
                'disponiveis' => $disponiveis,
                'vendidos' => $vendidos,
                'cortesias' => $cortesias,
                'utilizados' => $utilizados,
                'receita_bruta' => round((float) $receitaBruta, 2),
                'cupons_usados' => $cuponsUsados,
            ],
        ]);
    }
}

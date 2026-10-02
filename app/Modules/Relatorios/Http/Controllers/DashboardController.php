<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Relatorios\Services\Dashboard\DashboardFinanceiroService;
use App\Modules\Relatorios\Services\Dashboard\DashboardOperacionalService;
use App\Modules\Relatorios\Services\Dashboard\DashboardPeriod;
use App\Modules\Relatorios\Services\Dashboard\DashboardResumoService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Relatorios', description: 'Estatísticas e Resumo do Sistema')]
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardResumoService $resumoService,
        private readonly DashboardFinanceiroService $financeiroService,
        private readonly DashboardOperacionalService $operacionalService,
    ) {}

    #[OA\Get(
        path: '/api/dashboard/resumo',
        summary: 'KPIs do dashboard',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'mes', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-08')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'KPIs e leads ativos'),
        ]
    )]
    public function resumo(Request $request)
    {
        return response()->json(
            $this->resumoService->build($this->period($request))
        );
    }

    #[OA\Get(
        path: '/api/dashboard/financeiro',
        summary: 'Dados financeiros do dashboard',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'mes', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-08')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Gráficos e contas em atraso'),
        ]
    )]
    public function financeiro(Request $request)
    {
        return response()->json(
            $this->financeiroService->build($this->period($request))
        );
    }

    #[OA\Get(
        path: '/api/dashboard/operacional',
        summary: 'Dados operacionais do dashboard',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'mes', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-08')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Agenda, ausências, funil e ocupação'),
        ]
    )]
    public function operacional(Request $request)
    {
        return response()->json(
            $this->operacionalService->build($this->period($request))
        );
    }

    private function period(Request $request): DashboardPeriod
    {
        return DashboardPeriod::fromQuery($request->query('mes'));
    }
}

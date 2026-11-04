<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Services\GerarCarneService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Carne', description: 'Geração de carnê em PDF com QR Code PIX')]
class CarneController extends Controller
{
    #[OA\Post(
        path: '/api/carne/gerar',
        summary: 'Gerar carnê em PDF',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conta_ids'],
                properties: [
                    new OA\Property(property: 'conta_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'titulo', type: 'string', nullable: true),
                    new OA\Property(property: 'preferencia_dados', type: 'string', enum: ['aluno', 'responsavel'], default: 'aluno'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF do carnê gerado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação'),
            new OA\Response(response: 400, description: 'Configuração PIX não cadastrada'),
        ]
    )]
    public function gerar(Request $request, GerarCarneService $carne)
    {
        $data = $request->validate([
            'conta_ids' => 'required|array|min:1',
            'conta_ids.*' => ['integer', InstituicaoContext::existsRule('contas')],
            'titulo' => 'nullable|string|max:100',
            'preferencia_dados' => 'nullable|string|in:aluno,responsavel',
        ]);

        $contas = Conta::query()
            ->whereIn('id', $data['conta_ids'])
            ->where('tipo', 'receita')
            ->orderBy('data_vencimento')
            ->get();

        if ($contas->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma conta do tipo receita foi encontrada para os IDs informados.',
            ], 422);
        }

        return $carne->download($contas, $data['titulo'] ?? null, $data['preferencia_dados'] ?? 'aluno');
    }
}

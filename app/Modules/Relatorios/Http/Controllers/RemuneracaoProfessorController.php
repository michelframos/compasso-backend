<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pessoas\Models\Professor;
use App\Modules\Relatorios\Http\Requests\ExtratoProfessorRequest;
use App\Modules\Relatorios\Http\Requests\FecharMesProfessorRequest;
use App\Modules\Relatorios\Models\FechamentoProfessor;
use App\Modules\Relatorios\Services\ExtratoProfessorService;
use App\Modules\Relatorios\UseCases\FecharMesProfessorUseCase;
use App\Modules\Relatorios\UseCases\ReabrirMesProfessorUseCase;
use OpenApi\Attributes as OA;

class RemuneracaoProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/relatorios/professores/remuneracao',
        summary: 'Remuneração do mês de todos os professores (resumo e situação do fechamento)',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'mes', in: 'query', required: false, description: 'Competência (padrão: mês atual)', schema: new OA\Schema(type: 'string', example: '2026-09')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Professores em ordem alfabética', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'professor', type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'nome', type: 'string'),
                    ]),
                    new OA\Property(property: 'situacao', type: 'string', enum: ['em_andamento', 'aberto', 'fechado']),
                    new OA\Property(property: 'resumo', ref: '#/components/schemas/ResumoRemuneracao'),
                    new OA\Property(property: 'fechamento', ref: '#/components/schemas/FechamentoRemuneracao'),
                ])),
                new OA\Property(property: 'competencia', type: 'string', example: '2026-09'),
                new OA\Property(property: 'total', type: 'number', format: 'float', description: 'Soma de valor_total de todos os professores'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 422, description: 'Mês inválido ou futuro'),
        ]
    )]
    public function index(ExtratoProfessorRequest $request, ExtratoProfessorService $extratos)
    {
        $competencia = $request->competencia();
        $linhas = $extratos->visaoGeral($competencia);

        return response()->json([
            'data' => $linhas,
            'competencia' => $competencia->rotulo(),
            'total' => round(array_sum(array_map(fn (array $linha) => $linha['resumo']['valor_total'], $linhas)), 2),
        ]);
    }

    #[OA\Get(
        path: '/api/relatorios/professores/{professor}/extrato',
        summary: 'Extrato mensal de um professor (visão do administrador)',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'professor', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'mes', in: 'query', required: false, description: 'Competência (padrão: mês atual)', schema: new OA\Schema(type: 'string', example: '2026-09')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Extrato do mês', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ExtratoProfessor'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 404, description: 'Professor de outra instituição ou inexistente'),
            new OA\Response(response: 422, description: 'Mês inválido ou futuro'),
        ]
    )]
    public function extrato(ExtratoProfessorRequest $request, Professor $professor, ExtratoProfessorService $extratos)
    {
        return response()->json(['data' => $extratos->montar($professor, $request->competencia())]);
    }

    #[OA\Post(
        path: '/api/relatorios/professores/{professor}/fechamentos',
        summary: 'Fecha o mês do professor: congela o extrato e lança a despesa no Financeiro',
        description: 'Só meses encerrados. A despesa (categoria "Pagamento de professores") é criada quando o total é maior que zero e a escola tem o módulo financeiro.',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'professor', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['mes'],
            properties: [
                new OA\Property(property: 'mes', type: 'string', example: '2026-09'),
                new OA\Property(property: 'data_vencimento', type: 'string', format: 'date', nullable: true, description: 'Padrão: dia 5 do mês seguinte (ou hoje, se já passou)'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Extrato fechado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ExtratoProfessor'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 404, description: 'Professor de outra instituição ou inexistente'),
            new OA\Response(response: 422, description: 'Mês em andamento, futuro ou já fechado'),
        ]
    )]
    public function fechar(FecharMesProfessorRequest $request, Professor $professor, FecharMesProfessorUseCase $fecharMes, ExtratoProfessorService $extratos)
    {
        $competencia = $request->competencia();
        $fecharMes->execute($professor, $competencia, $request->user(), $request->validated('data_vencimento'));

        return response()->json(['data' => $extratos->montar($professor, $competencia)], 201);
    }

    #[OA\Delete(
        path: '/api/relatorios/professores/{professor}/fechamentos/{fechamento}',
        summary: 'Reabre o mês: desfaz o fechamento e exclui a despesa ainda não paga',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'professor', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fechamento', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Mês reaberto'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 404, description: 'Fechamento de outro professor ou instituição'),
            new OA\Response(response: 422, description: 'A despesa já tem pagamentos registrados'),
        ]
    )]
    public function reabrir(Professor $professor, FechamentoProfessor $fechamento, ReabrirMesProfessorUseCase $reabrirMes)
    {
        abort_unless((int) $fechamento->id_professor === (int) $professor->id, 404);

        $reabrirMes->execute($fechamento);

        return response()->noContent();
    }
}

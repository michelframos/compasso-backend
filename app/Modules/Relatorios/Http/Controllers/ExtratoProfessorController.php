<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Relatorios\Http\Requests\ExtratoProfessorRequest;
use App\Modules\Relatorios\Services\ExtratoProfessorService;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TurmaRemuneracao',
    type: 'object',
    nullable: true,
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'descricao', type: 'string', nullable: true, example: 'Turma A'),
        new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string', example: 'Violão')]),
        new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string', example: 'Iniciante')]),
    ]
)]
#[OA\Schema(
    schema: 'ResumoRemuneracao',
    type: 'object',
    properties: [
        new OA\Property(property: 'salario_fixo', type: 'number', format: 'float', example: 1000),
        new OA\Property(property: 'total_aulas', type: 'integer', example: 12),
        new OA\Property(property: 'total_horas', type: 'number', format: 'float', example: 15.5),
        new OA\Property(property: 'valor_hora_aula', type: 'number', format: 'float', description: 'Total de hora-aula do mês', example: 775),
        new OA\Property(property: 'base_comissao', type: 'number', format: 'float', description: 'Total pago pelos alunos no mês', example: 2400),
        new OA\Property(property: 'valor_comissao', type: 'number', format: 'float', example: 480),
        new OA\Property(property: 'valor_total', type: 'number', format: 'float', example: 2255),
    ]
)]
#[OA\Schema(
    schema: 'FechamentoRemuneracao',
    type: 'object',
    nullable: true,
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'fechado_em', type: 'string', format: 'date-time'),
        new OA\Property(property: 'fechado_por', type: 'string', nullable: true, example: 'Administrador'),
        new OA\Property(property: 'conta', type: 'object', nullable: true, description: 'Despesa gerada no Financeiro (nula sem o módulo ou com total zero)', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 90),
            new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'pago', 'pago_parcialmente', 'vencido', 'cancelado']),
            new OA\Property(property: 'valor', type: 'number', format: 'float', example: 2255),
            new OA\Property(property: 'data_vencimento', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'data_pagamento', type: 'string', format: 'date', nullable: true),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'ExtratoProfessor',
    type: 'object',
    properties: [
        new OA\Property(property: 'competencia', type: 'string', example: '2026-09'),
        new OA\Property(property: 'periodo', type: 'object', properties: [
            new OA\Property(property: 'inicio', type: 'string', format: 'date'),
            new OA\Property(property: 'fim', type: 'string', format: 'date'),
        ]),
        new OA\Property(property: 'situacao', type: 'string', enum: ['em_andamento', 'aberto', 'fechado'], description: 'em_andamento: mês corrente (parcial); aberto: encerrado aguardando fechamento; fechado: valores congelados'),
        new OA\Property(property: 'professor', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'regras', type: 'object', description: 'Valores do cadastro do professor usados como padrão', properties: [
            new OA\Property(property: 'salario_fixo', type: 'number', format: 'float'),
            new OA\Property(property: 'valor_hora_aula', type: 'number', format: 'float'),
            new OA\Property(property: 'comissao', type: 'number', format: 'float', description: 'Percentual'),
        ]),
        new OA\Property(property: 'resumo', ref: '#/components/schemas/ResumoRemuneracao'),
        new OA\Property(property: 'aulas', type: 'array', description: 'Aulas concluídas no mês', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'data', type: 'string', format: 'date'),
            new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
            new OA\Property(property: 'hora_termino', type: 'string', example: '15:30'),
            new OA\Property(property: 'tipo', type: 'string', nullable: true, example: 'regular'),
            new OA\Property(property: 'horas', type: 'number', format: 'float', example: 1.5),
            new OA\Property(property: 'valor_hora', type: 'number', format: 'float', description: 'Congelado na conclusão; turma sobrepõe professor', example: 50),
            new OA\Property(property: 'valor', type: 'number', format: 'float', example: 75),
            new OA\Property(property: 'turma', ref: '#/components/schemas/TurmaRemuneracao'),
            new OA\Property(property: 'curso', type: 'object', nullable: true, description: 'Curso da aula individual', properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'aluno', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nome', type: 'string'),
            ]),
        ])),
        new OA\Property(property: 'comissoes', type: 'array', description: 'Comissão por turma (turma nula = aulas individuais)', items: new OA\Items(properties: [
            new OA\Property(property: 'chave', type: 'string', example: 'turma:3'),
            new OA\Property(property: 'turma', ref: '#/components/schemas/TurmaRemuneracao'),
            new OA\Property(property: 'base', type: 'number', format: 'float', description: 'Total pago no mês', example: 1200),
            new OA\Property(property: 'percentual', type: 'number', format: 'float', nullable: true, description: 'Nulo quando houve percentuais diferentes no grupo', example: 20),
            new OA\Property(property: 'valor', type: 'number', format: 'float', example: 240),
            new OA\Property(property: 'pagamentos', type: 'integer', example: 4),
            new OA\Property(property: 'alunos', type: 'integer', example: 4),
        ])),
        new OA\Property(property: 'fechamento', ref: '#/components/schemas/FechamentoRemuneracao'),
    ]
)]
class ExtratoProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/extrato',
        summary: 'Meus ganhos: extrato mensal do professor logado (fixo + hora-aula + comissão)',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'mes', in: 'query', required: false, description: 'Competência (padrão: mês atual). Meses futuros não são aceitos.', schema: new OA\Schema(type: 'string', example: '2026-09')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Extrato do mês', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ExtratoProfessor'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas professores'),
            new OA\Response(response: 422, description: 'Mês inválido ou futuro'),
        ]
    )]
    public function show(ExtratoProfessorRequest $request, ExtratoProfessorService $extratos)
    {
        return response()->json([
            'data' => $extratos->montar($request->user()->professor, $request->competencia()),
        ]);
    }
}

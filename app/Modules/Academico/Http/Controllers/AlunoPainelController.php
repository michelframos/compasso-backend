<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Services\PainelAlunoService;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AlunoPainelController extends Controller
{
    public function __construct(
        private readonly AlunoAlvoResolverPort $alunos,
        private readonly PainelAlunoService $painel,
    ) {}

    #[OA\Get(
        path: '/api/aluno/me/painel',
        summary: 'Painel do aluno: próxima aula, financeiro, apresentação e avisos',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Painel do aluno',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'data_referencia', type: 'string', format: 'date', example: '2026-10-26'),
                        new OA\Property(property: 'proxima_aula', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'origem', type: 'string', enum: ['aula', 'horario'], example: 'aula'),
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-10-27'),
                            new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
                            new OA\Property(property: 'hora_termino', type: 'string', example: '15:00'),
                            new OA\Property(property: 'status', type: 'string', nullable: true, example: 'agendada'),
                            new OA\Property(property: 'tipo', type: 'string', nullable: true, example: 'regular'),
                            new OA\Property(property: 'turma', type: 'string', example: 'Turma Ana'),
                            new OA\Property(property: 'dia_semana', type: 'string', nullable: true, example: 'terca'),
                        ]),
                        new OA\Property(property: 'proximas_aulas', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'financeiro', type: 'object', properties: [
                            new OA\Property(property: 'disponivel', type: 'boolean', example: true),
                            new OA\Property(property: 'proxima_mensalidade', type: 'object', nullable: true, properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 4),
                                new OA\Property(property: 'descricao', type: 'string', example: 'Mensalidade de outubro'),
                                new OA\Property(property: 'valor', type: 'number', format: 'float', example: 180),
                                new OA\Property(property: 'data_vencimento', type: 'string', format: 'date', example: '2026-10-20'),
                                new OA\Property(property: 'status', type: 'string', example: 'pendente'),
                                new OA\Property(property: 'situacao', type: 'string', enum: ['atrasada', 'vence_hoje', 'a_vencer'], example: 'atrasada'),
                            ]),
                            new OA\Property(property: 'em_aberto', type: 'integer', example: 2),
                            new OA\Property(property: 'atrasadas', type: 'integer', example: 1),
                            new OA\Property(property: 'valor_em_aberto', type: 'number', format: 'float', example: 180),
                            new OA\Property(property: 'valor_atrasado', type: 'number', format: 'float', example: 100),
                        ]),
                        new OA\Property(property: 'apresentacao', type: 'object', properties: [
                            new OA\Property(property: 'disponivel', type: 'boolean', example: true),
                            new OA\Property(property: 'proxima', type: 'object', nullable: true, properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 8),
                                new OA\Property(property: 'titulo_musica', type: 'string', example: 'Peça da Ana'),
                                new OA\Property(property: 'espetaculo', type: 'string', example: 'Recital'),
                                new OA\Property(property: 'data_evento', type: 'string', format: 'date', example: '2026-11-20'),
                                new OA\Property(property: 'local', type: 'string', nullable: true, example: 'Teatro'),
                            ]),
                        ]),
                        new OA\Property(property: 'avisos', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'tipo', type: 'string', enum: ['cancelada', 'remarcada'], example: 'cancelada'),
                            new OA\Property(property: 'titulo', type: 'string', example: 'Aula cancelada'),
                            new OA\Property(property: 'mensagem', type: 'string', example: 'A aula de Turma Ana de 20/10, 14:00–15:00 foi cancelada.'),
                            new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-10-20'),
                        ])),
                    ]),
                ])
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição, sem vínculo ou senha pendente'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);

        return response()->json(['data' => $this->painel->montar($aluno)]);
    }
}

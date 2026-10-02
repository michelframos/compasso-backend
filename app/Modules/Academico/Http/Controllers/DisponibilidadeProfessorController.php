<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Disponibilidade\ProfessoresLivresRequest;
use App\Modules\Academico\Http\Requests\Disponibilidade\SalvarDisponibilidadesRequest;
use App\Modules\Academico\Http\Resources\DisponibilidadeProfessorResource;
use App\Modules\Academico\Models\DisponibilidadeProfessor;
use App\Modules\Academico\Queries\ProfessoresLivresQuery;
use App\Modules\Academico\UseCases\Disponibilidade\SalvarDisponibilidadesUseCase;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DisponibilidadeProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/disponibilidades',
        summary: 'Disponibilidade semanal do professor autenticado',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Janelas de segunda a domingo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DisponibilidadeProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
        ]
    )]
    public function minhas(Request $request)
    {
        return DisponibilidadeProfessorResource::collection(
            DisponibilidadeProfessor::doProfessor($request->user()->professor->id)
        );
    }

    #[OA\Put(
        path: '/api/professor/me/disponibilidades',
        summary: 'Substituir a disponibilidade semanal do professor autenticado',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SalvarDisponibilidadesRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Disponibilidade salva', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DisponibilidadeProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
            new OA\Response(response: 422, description: 'Dia inválido, término antes do início ou janelas sobrepostas'),
        ]
    )]
    public function salvar(SalvarDisponibilidadesRequest $request, SalvarDisponibilidadesUseCase $salvar)
    {
        return DisponibilidadeProfessorResource::collection(
            $salvar->execute($request->user()->professor, $request->validated('disponibilidades'))
        );
    }

    #[OA\Get(
        path: '/api/professores/{professor}/disponibilidades',
        summary: 'Disponibilidade semanal de um professor (secretaria)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'professor', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Janelas de segunda a domingo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DisponibilidadeProfessorResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 404, description: 'Professor não encontrado'),
        ]
    )]
    public function doProfessor(Professor $professor)
    {
        return DisponibilidadeProfessorResource::collection(DisponibilidadeProfessor::doProfessor($professor->id));
    }

    #[OA\Get(
        path: '/api/agenda/professores-livres',
        summary: 'Professores com a situação de agenda num horário (para escolher substituto)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'data', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'hora_inicio', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '14:00')),
            new OA\Parameter(name: 'hora_termino', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '15:00')),
            new OA\Parameter(name: 'ignorar_aula', in: 'query', required: false, description: 'Aula que não conta como conflito (a que está sendo remanejada)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Livres primeiro, depois fora da disponibilidade, por fim com conflito', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'nome', type: 'string', nullable: true),
                    new OA\Property(property: 'conflito', type: 'boolean', description: 'Já tem aula no horário'),
                    new OA\Property(property: 'fora_disponibilidade', type: 'boolean', description: 'Cadastrou disponibilidade e o horário fica fora dela'),
                ])),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 422, description: 'Horário inválido'),
        ]
    )]
    public function professoresLivres(ProfessoresLivresRequest $request, ProfessoresLivresQuery $query)
    {
        $dados = $request->validated();

        return response()->json(['data' => $query->build(
            $dados['data'],
            $dados['hora_inicio'],
            $dados['hora_termino'],
            isset($dados['ignorar_aula']) ? [(int) $dados['ignorar_aula']] : []
        )]);
    }
}

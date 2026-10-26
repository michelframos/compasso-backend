<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Resources\CertificadoResource;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\UseCases\Certificado\EmitirCertificadoUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class CertificadoController extends Controller
{
    #[OA\Get(
        path: '/api/certificados',
        summary: 'Certificados emitidos na escola',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de certificados', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CertificadoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
        ]
    )]
    public function index()
    {
        $certificados = Certificado::query()
            ->with(['matricula.aluno.usuario', 'matricula.turma.curso', 'matricula.turma.nivel', 'matricula.curso', 'matricula.nivel'])
            ->orderByDesc('id')
            ->get();

        return CertificadoResource::collection($certificados);
    }

    #[OA\Post(
        path: '/api/matriculas/{matricula}/certificados',
        summary: 'Emitir certificado de conclusão da matrícula',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'matricula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Certificado emitido', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CertificadoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
            new OA\Response(response: 404, description: 'Matrícula não encontrada'),
            new OA\Response(response: 422, description: 'Matrícula não elegível ou certificado já emitido'),
        ]
    )]
    public function store(Matricula $matricula, EmitirCertificadoUseCase $emitir)
    {
        Gate::authorize('emitir', [Certificado::class, $matricula]);

        $matricula->load(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'historicos']);

        return (new CertificadoResource($emitir->execute($matricula)))
            ->response()
            ->setStatusCode(201);
    }
}

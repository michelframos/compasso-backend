<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Certificado\EmitirLoteCertificadosRequest;
use App\Modules\Academico\Http\Resources\CertificadoResource;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Queries\ListarMatriculasElegiveisCertificado;
use App\Modules\Academico\Services\CertificadoPdfService;
use App\Modules\Academico\UseCases\Certificado\EmitirCertificadoUseCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

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
        Gate::authorize('viewAnyStaff', Certificado::class);

        $certificados = Certificado::query()
            ->with(['matricula.aluno.usuario', 'matricula.turma.curso', 'matricula.turma.nivel', 'matricula.curso', 'matricula.nivel'])
            ->orderByDesc('id')
            ->get();

        return CertificadoResource::collection($certificados);
    }

    #[OA\Get(
        path: '/api/certificados/elegiveis',
        summary: 'Matrículas elegíveis para emissão de certificado',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de matrículas elegíveis'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
        ]
    )]
    public function elegiveis(ListarMatriculasElegiveisCertificado $listar)
    {
        Gate::authorize('viewAnyStaff', Certificado::class);

        return response()->json(['data' => $listar->execute()->values()]);
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

    #[OA\Post(
        path: '/api/certificados/emitir-lote',
        summary: 'Emitir certificados em lote',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EmitirLoteCertificadosRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Resultado parcial da emissão em lote'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
            new OA\Response(response: 422, description: 'Payload inválido'),
        ]
    )]
    public function emitirLote(EmitirLoteCertificadosRequest $request, EmitirCertificadoUseCase $emitir)
    {
        Gate::authorize('viewAnyStaff', Certificado::class);

        $emitidos = [];
        $falhas = [];

        $matriculas = Matricula::query()
            ->whereIn('id', $request->validated('matricula_ids'))
            ->with(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'historicos'])
            ->get()
            ->keyBy('id');

        foreach ($request->validated('matricula_ids') as $idMatricula) {
            $matricula = $matriculas->get($idMatricula);

            if (! $matricula) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'Matrícula não encontrada.',
                ];

                continue;
            }

            try {
                Gate::authorize('emitir', [Certificado::class, $matricula]);
                $emitidos[] = new CertificadoResource($emitir->execute($matricula));
            } catch (ValidationException $e) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => collect($e->errors())->flatten()->first() ?? 'Não foi possível emitir.',
                ];
            } catch (Throwable $e) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'Não foi possível emitir o certificado.',
                ];
            }
        }

        return response()->json([
            'data' => [
                'emitidos' => $emitidos,
                'falhas' => $falhas,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/certificados/{certificado}/pdf',
        summary: 'PDF do certificado (secretaria)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'certificado', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF do certificado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
            new OA\Response(response: 404, description: 'Certificado não encontrado'),
        ]
    )]
    public function pdf(Certificado $certificado, CertificadoPdfService $pdf)
    {
        Gate::authorize('baixarPdfStaff', $certificado);

        return $pdf->download($certificado);
    }
}

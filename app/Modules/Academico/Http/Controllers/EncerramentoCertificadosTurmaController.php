<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Certificado\EncerrarCertificadosTurmaRequest;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListarEncerramentoCertificadosTurma;
use App\Modules\Academico\UseCases\Certificado\EncerrarCertificadosTurmaUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class EncerramentoCertificadosTurmaController extends Controller
{
    #[OA\Get(
        path: '/api/turmas/{turma}/encerramento-certificados',
        summary: 'Preview de aptos e inaptos para emissão ao encerrar a turma',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'turma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listas de aptos e inaptos'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
            new OA\Response(response: 404, description: 'Turma não encontrada'),
        ]
    )]
    public function preview(Turma $turma, ListarEncerramentoCertificadosTurma $listar)
    {
        Gate::authorize('viewAnyStaff', Certificado::class);

        return response()->json(['data' => $listar->execute($turma)]);
    }

    #[OA\Post(
        path: '/api/turmas/{turma}/encerramento-certificados',
        summary: 'Completar matrículas aptas e emitir certificados da turma',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'turma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EncerrarCertificadosTurmaRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Resultado parcial da emissão'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
            new OA\Response(response: 404, description: 'Turma não encontrada'),
            new OA\Response(response: 422, description: 'Turma não concluída ou payload inválido'),
        ]
    )]
    public function emitir(EncerrarCertificadosTurmaRequest $request, Turma $turma, EncerrarCertificadosTurmaUseCase $encerrar)
    {
        Gate::authorize('viewAnyStaff', Certificado::class);

        $resultado = $encerrar->execute($turma, $request->validated('matricula_ids'));

        return response()->json(['data' => $resultado]);
    }
}

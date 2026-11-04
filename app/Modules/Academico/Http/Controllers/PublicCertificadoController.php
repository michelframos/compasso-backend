<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Resources\CertificadoPublicoResource;
use App\Modules\Academico\Models\Certificado;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicCertificadoController extends Controller
{
    #[OA\Get(
        path: '/api/public/certificados/{codigo}',
        summary: 'Verificar autenticidade de um certificado pelo código',
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'codigo', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Certificado válido', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CertificadoPublicoResource'),
            ])),
            new OA\Response(response: 404, description: 'Código não encontrado'),
        ]
    )]
    public function show(string $codigo)
    {
        $certificado = Certificado::query()
            ->with([
                'instituicao',
                'matricula.aluno.usuario',
                'matricula.turma.curso',
                'matricula.turma.nivel',
                'matricula.curso',
                'matricula.nivel',
            ])
            ->where('codigo_verificacao', $codigo)
            ->first();

        if ($certificado === null) {
            throw new HttpException(404, 'Certificado não encontrado.');
        }

        return new CertificadoPublicoResource($certificado);
    }
}

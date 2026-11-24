<?php

namespace App\Modules\Bilheteria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bilheteria\Http\Resources\IngressoPublicoResource;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\Services\IngressoPdfService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicIngressoController extends Controller
{
    #[OA\Get(
        path: '/api/public/ingressos/{codigo}',
        summary: 'Visualizar ingresso público pelo código QR',
        tags: ['Bilheteria'],
        parameters: [
            new OA\Parameter(name: 'codigo', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ingresso encontrado'),
            new OA\Response(response: 404, description: 'Não encontrado ou ainda disponível'),
        ]
    )]
    public function show(string $codigo)
    {
        return new IngressoPublicoResource($this->encontrarPublico($codigo));
    }

    #[OA\Get(
        path: '/api/public/ingressos/{codigo}/pdf',
        summary: 'PDF público do ingresso',
        tags: ['Bilheteria'],
        parameters: [
            new OA\Parameter(name: 'codigo', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF do ingresso'),
            new OA\Response(response: 404, description: 'Não encontrado ou ainda disponível'),
        ]
    )]
    public function pdf(string $codigo, IngressoPdfService $pdf)
    {
        return $pdf->download($this->encontrarPublico($codigo));
    }

    private function encontrarPublico(string $codigo): Ingresso
    {
        $ingresso = Ingresso::query()
            ->withoutInstituicaoScope()
            ->with(['espetaculo', 'template', 'instituicao'])
            ->where('codigo_qr', $codigo)
            ->first();

        if ($ingresso === null || $ingresso->status === Ingresso::STATUS_DISPONIVEL) {
            throw new HttpException(404, 'Ingresso não encontrado.');
        }

        return $ingresso;
    }
}

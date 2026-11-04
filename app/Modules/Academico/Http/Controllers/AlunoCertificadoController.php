<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Certificado\ListCertificadosDoAlunoRequest;
use App\Modules\Academico\Http\Resources\CertificadoDoAlunoResource;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Services\CertificadoPdfService;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoCertificadoController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/certificados',
        summary: 'Certificados de conclusão do aluno',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Certificados, do mais recente ao mais antigo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CertificadoDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo ou outra instituição'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListCertificadosDoAlunoRequest $request)
    {
        $aluno = $this->aluno($request);
        Gate::authorize('viewAnyDoAluno', [Certificado::class, $aluno->id]);

        $certificados = Certificado::query()
            ->with(['matricula.turma.curso', 'matricula.turma.nivel', 'matricula.curso', 'matricula.nivel'])
            ->whereHas('matricula', fn ($q) => $q->where('id_aluno', $aluno->id))
            ->orderByDesc('id')
            ->get();

        return CertificadoDoAlunoResource::collection($certificados);
    }

    #[OA\Get(
        path: '/api/aluno/me/certificados/{certificado}/pdf',
        summary: 'PDF do certificado de conclusão',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'certificado', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF do certificado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Certificado de outro aluno'),
            new OA\Response(response: 404, description: 'Certificado não encontrado'),
        ]
    )]
    public function pdf(ListCertificadosDoAlunoRequest $request, Certificado $certificado, CertificadoPdfService $pdf)
    {
        $certificado->loadMissing('matricula');
        $aluno = $this->aluno($request);

        if ((int) $certificado->matricula?->id_aluno !== (int) $aluno->id) {
            throw new HttpException(403, 'Acesso restrito: este certificado não é seu.');
        }

        Gate::authorize('view', $certificado);

        return $pdf->download($certificado);
    }

    private function aluno(ListCertificadosDoAlunoRequest $request)
    {
        $idInformado = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idInformado);
    }
}

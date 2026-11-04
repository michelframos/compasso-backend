<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Academico\Http\Requests\TemplateCertificado\StoreTemplateCertificadoRequest;
use App\Modules\Academico\Http\Requests\TemplateCertificado\UpdateTemplateCertificadoRequest;
use App\Modules\Academico\Http\Requests\TemplateCertificado\UploadElementoTemplateCertificadoRequest;
use App\Modules\Academico\Http\Requests\TemplateCertificado\UploadFundoTemplateCertificadoRequest;
use App\Modules\Academico\Http\Resources\TemplateCertificadoResource;
use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Services\TemplateCertificadoFundoService;
use App\Modules\Academico\Support\LayoutCertificado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class TemplateCertificadoController extends Controller
{
    use ResolvesSorting;

    public function __construct(private readonly TemplateCertificadoFundoService $fundos) {}

    #[OA\Get(path: '/api/templates-certificados', summary: 'Listar templates de certificado', security: [['sanctum' => []]], tags: ['Academico'])]
    public function index()
    {
        Gate::authorize('viewAny', TemplateCertificado::class);

        $search = request('search');
        $query = TemplateCertificado::query();

        if ($search) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $templates = $this->applySorting($query, ['id', 'nome', 'created_at'], 'nome')->paginate(50);

        return TemplateCertificadoResource::collection($templates);
    }

    #[OA\Post(path: '/api/templates-certificados', summary: 'Criar template de certificado', security: [['sanctum' => []]], tags: ['Academico'])]
    public function store(StoreTemplateCertificadoRequest $request)
    {
        Gate::authorize('create', TemplateCertificado::class);

        $dados = $request->validated();
        $dados['conteudo'] = LayoutCertificado::encode(LayoutCertificado::parse($dados['conteudo'] ?? null));

        $template = TemplateCertificado::create($dados);

        return (new TemplateCertificadoResource($template))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(path: '/api/templates-certificados/{templateCertificado}', summary: 'Exibir template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function show(TemplateCertificado $templateCertificado)
    {
        Gate::authorize('view', $templateCertificado);

        return new TemplateCertificadoResource($templateCertificado);
    }

    #[OA\Put(path: '/api/templates-certificados/{templateCertificado}', summary: 'Atualizar template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function update(UpdateTemplateCertificadoRequest $request, TemplateCertificado $templateCertificado)
    {
        Gate::authorize('update', $templateCertificado);

        $dados = $request->validated();
        if (array_key_exists('conteudo', $dados)) {
            $dados['conteudo'] = LayoutCertificado::encode(LayoutCertificado::parse($dados['conteudo']));
        }

        $templateCertificado->update($dados);

        return new TemplateCertificadoResource($templateCertificado->fresh());
    }

    #[OA\Delete(path: '/api/templates-certificados/{templateCertificado}', summary: 'Remover template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function destroy(TemplateCertificado $templateCertificado)
    {
        Gate::authorize('delete', $templateCertificado);

        $this->fundos->remover($templateCertificado->fundo_path);
        $templateCertificado->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(path: '/api/templates-certificados/{templateCertificado}/duplicar', summary: 'Duplicar template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function duplicar(TemplateCertificado $templateCertificado)
    {
        Gate::authorize('create', TemplateCertificado::class);
        Gate::authorize('view', $templateCertificado);

        $copia = TemplateCertificado::create([
            'nome' => $this->nomeCopia($templateCertificado->nome),
            'conteudo' => $templateCertificado->conteudo,
            'fundo_path' => $this->fundos->copiarFundo($templateCertificado->fundo_path),
        ]);

        return (new TemplateCertificadoResource($copia))
            ->response()
            ->setStatusCode(201);
    }

    private function nomeCopia(string $nomeOriginal): string
    {
        $base = mb_substr(trim($nomeOriginal).' (cópia)', 0, 150);
        $candidato = $base;
        $sufixo = 2;

        while (TemplateCertificado::query()->where('nome', $candidato)->exists()) {
            $sufixoTexto = ' (cópia '.$sufixo.')';
            $tronco = mb_substr(trim($nomeOriginal), 0, max(1, 150 - mb_strlen($sufixoTexto)));
            $candidato = $tronco.$sufixoTexto;
            $sufixo++;
        }

        return $candidato;
    }

    #[OA\Post(path: '/api/templates-certificados/{templateCertificado}/fundo', summary: 'Enviar fundo do template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function uploadFundo(UploadFundoTemplateCertificadoRequest $request, TemplateCertificado $templateCertificado)
    {
        Gate::authorize('update', $templateCertificado);

        $caminho = $this->fundos->armazenar($templateCertificado, $request->file('fundo'));
        $templateCertificado->update(['fundo_path' => $caminho]);

        return new TemplateCertificadoResource($templateCertificado->fresh());
    }

    #[OA\Delete(path: '/api/templates-certificados/{templateCertificado}/fundo', summary: 'Remover fundo do template', security: [['sanctum' => []]], tags: ['Academico'])]
    public function destroyFundo(TemplateCertificado $templateCertificado)
    {
        Gate::authorize('update', $templateCertificado);

        $this->fundos->remover($templateCertificado->fundo_path);
        $templateCertificado->update(['fundo_path' => null]);

        return new TemplateCertificadoResource($templateCertificado->fresh());
    }

    #[OA\Post(path: '/api/templates-certificados/{templateCertificado}/elementos', summary: 'Enviar imagem de elemento do canvas', security: [['sanctum' => []]], tags: ['Academico'])]
    public function uploadElemento(UploadElementoTemplateCertificadoRequest $request, TemplateCertificado $templateCertificado)
    {
        Gate::authorize('update', $templateCertificado);

        $caminho = $this->fundos->armazenarElemento($request->file('imagem'));

        return response()->json([
            'data' => [
                'src' => $caminho,
                'url' => Storage::disk(TemplateCertificado::DISCO_FUNDO)->url($caminho),
            ],
        ], 201);
    }
}

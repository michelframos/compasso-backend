<?php

namespace App\Modules\Bilheteria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bilheteria\Http\Requests\StoreTemplateIngressoRequest;
use App\Modules\Bilheteria\Http\Requests\UpdateTemplateIngressoRequest;
use App\Modules\Bilheteria\Http\Resources\TemplateIngressoResource;
use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Bilheteria\Services\TemplateIngressoFundoService;
use App\Modules\Bilheteria\Support\LayoutIngresso;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TemplateIngressoController extends Controller
{
    use ResolvesSorting;

    public function __construct(private readonly TemplateIngressoFundoService $fundos) {}

    public function index()
    {
        Gate::authorize('viewAny', TemplateIngresso::class);

        $query = TemplateIngresso::query();
        if ($search = request('search')) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $templates = $this->applySorting($query, ['id', 'nome', 'created_at'], 'nome')->paginate(50);

        return TemplateIngressoResource::collection($templates);
    }

    public function store(StoreTemplateIngressoRequest $request)
    {
        Gate::authorize('create', TemplateIngresso::class);

        $dados = $request->validated();
        $dados['conteudo'] = LayoutIngresso::encode(LayoutIngresso::parse($dados['conteudo'] ?? null));

        $template = TemplateIngresso::create($dados);

        return (new TemplateIngressoResource($template))->response()->setStatusCode(201);
    }

    public function show(TemplateIngresso $templateIngresso)
    {
        Gate::authorize('view', $templateIngresso);

        return new TemplateIngressoResource($templateIngresso);
    }

    public function update(UpdateTemplateIngressoRequest $request, TemplateIngresso $templateIngresso)
    {
        Gate::authorize('update', $templateIngresso);

        $dados = $request->validated();
        if (array_key_exists('conteudo', $dados)) {
            $dados['conteudo'] = LayoutIngresso::encode(LayoutIngresso::parse($dados['conteudo']));
        }

        $templateIngresso->update($dados);

        return new TemplateIngressoResource($templateIngresso->fresh());
    }

    public function destroy(TemplateIngresso $templateIngresso)
    {
        Gate::authorize('delete', $templateIngresso);

        $this->fundos->remover($templateIngresso->fundo_path);
        $templateIngresso->delete();

        return response()->json(null, 204);
    }

    public function uploadFundo(Request $request, TemplateIngresso $templateIngresso)
    {
        Gate::authorize('update', $templateIngresso);

        $request->validate(['fundo' => ['required', 'image', 'max:5120']]);

        $this->fundos->remover($templateIngresso->fundo_path);
        $path = $this->fundos->salvarFundo($request->file('fundo'));
        $templateIngresso->update(['fundo_path' => $path]);

        return new TemplateIngressoResource($templateIngresso->fresh());
    }

    public function removerFundo(TemplateIngresso $templateIngresso)
    {
        Gate::authorize('update', $templateIngresso);

        $this->fundos->remover($templateIngresso->fundo_path);
        $templateIngresso->update(['fundo_path' => null]);

        return new TemplateIngressoResource($templateIngresso->fresh());
    }

    public function uploadElemento(Request $request, TemplateIngresso $templateIngresso)
    {
        Gate::authorize('update', $templateIngresso);

        $request->validate(['imagem' => ['required', 'image', 'max:5120']]);

        $path = $this->fundos->salvarElemento($request->file('imagem'));

        return response()->json([
            'data' => [
                'src' => $path,
                'url' => \Illuminate\Support\Facades\Storage::disk(TemplateIngresso::DISCO_FUNDO)->url($path),
            ],
        ], 201);
    }
}

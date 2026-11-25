<?php

namespace App\Modules\Bilheteria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bilheteria\Http\Requests\StoreCupomIngressoRequest;
use App\Modules\Bilheteria\Http\Requests\UpdateCupomIngressoRequest;
use App\Modules\Bilheteria\Http\Resources\CupomIngressoResource;
use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use Illuminate\Support\Facades\Gate;

class CupomIngressoController extends Controller
{
    use ResolvesSorting;

    public function index()
    {
        Gate::authorize('viewAny', CupomIngresso::class);

        $query = CupomIngresso::query()->with('espetaculos');

        if (request()->filled('id_espetaculo')) {
            $idEspetaculo = (int) request('id_espetaculo');
            $query->where(function ($q) use ($idEspetaculo) {
                $q->whereDoesntHave('espetaculos')
                    ->orWhereHas('espetaculos', fn ($sub) => $sub->where('espetaculos.id', $idEspetaculo));
            });
        }

        if ($search = request('search')) {
            $query->where('codigo', 'like', "%{$search}%");
        }

        $cupons = $this->applySorting($query, ['id', 'codigo', 'created_at'], 'codigo')->paginate(50);

        return CupomIngressoResource::collection($cupons);
    }

    public function store(StoreCupomIngressoRequest $request)
    {
        Gate::authorize('create', CupomIngresso::class);

        $dados = $request->validated();
        $idsEspetaculos = $dados['ids_espetaculos'] ?? [];
        unset($dados['ids_espetaculos']);

        $cupom = CupomIngresso::create($dados);
        $cupom->espetaculos()->sync($idsEspetaculos);

        return (new CupomIngressoResource($cupom->load('espetaculos')))->response()->setStatusCode(201);
    }

    public function show(CupomIngresso $cupomIngresso)
    {
        Gate::authorize('view', $cupomIngresso);

        return new CupomIngressoResource($cupomIngresso->load('espetaculos'));
    }

    public function update(UpdateCupomIngressoRequest $request, CupomIngresso $cupomIngresso)
    {
        Gate::authorize('update', $cupomIngresso);

        $dados = $request->validated();
        $sincronizarEspetaculos = array_key_exists('ids_espetaculos', $dados);
        $idsEspetaculos = $dados['ids_espetaculos'] ?? [];
        unset($dados['ids_espetaculos']);

        $cupomIngresso->update($dados);

        if ($sincronizarEspetaculos) {
            $cupomIngresso->espetaculos()->sync($idsEspetaculos);
        }

        return new CupomIngressoResource($cupomIngresso->fresh()->load('espetaculos'));
    }

    public function destroy(CupomIngresso $cupomIngresso)
    {
        Gate::authorize('delete', $cupomIngresso);

        $cupomIngresso->delete();

        return response()->json(null, 204);
    }
}

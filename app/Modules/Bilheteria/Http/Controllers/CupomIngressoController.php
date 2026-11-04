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

        $query = CupomIngresso::query()->with('espetaculo');

        if (request()->filled('id_espetaculo')) {
            $query->where(function ($q) {
                $q->whereNull('id_espetaculo')
                    ->orWhere('id_espetaculo', (int) request('id_espetaculo'));
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

        $cupom = CupomIngresso::create($request->validated());

        return (new CupomIngressoResource($cupom->load('espetaculo')))->response()->setStatusCode(201);
    }

    public function show(CupomIngresso $cupomIngresso)
    {
        Gate::authorize('view', $cupomIngresso);

        return new CupomIngressoResource($cupomIngresso->load('espetaculo'));
    }

    public function update(UpdateCupomIngressoRequest $request, CupomIngresso $cupomIngresso)
    {
        Gate::authorize('update', $cupomIngresso);

        $cupomIngresso->update($request->validated());

        return new CupomIngressoResource($cupomIngresso->fresh()->load('espetaculo'));
    }

    public function destroy(CupomIngresso $cupomIngresso)
    {
        Gate::authorize('delete', $cupomIngresso);

        $cupomIngresso->delete();

        return response()->json(null, 204);
    }
}

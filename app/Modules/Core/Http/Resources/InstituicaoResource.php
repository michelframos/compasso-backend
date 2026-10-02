<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InstituicaoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'nome_fantasia', type: 'string'),
        new OA\Property(property: 'razao_social', type: 'string', nullable: true),
        new OA\Property(property: 'role', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'assinatura_status', type: 'string', nullable: true),
        new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', nullable: true),
        new OA\Property(property: 'limite_alunos', type: 'integer', nullable: true),
        new OA\Property(property: 'modulos', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'em_trial', type: 'boolean'),
    ]
)]
class InstituicaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entitlements = app(PlanoEntitlementResolverInterface::class)->resolve($this->resource);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome_fantasia' => $this->nome_fantasia,
            'razao_social' => $this->razao_social,
            'role' => $this->when(isset($this->pivot), fn () => $this->pivot?->role),
            'status' => $this->status,
            'assinatura_status' => $this->assinatura_status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'id_plano_assinatura' => $this->id_plano_assinatura,
            'limite_alunos' => $entitlements['limite_alunos'],
            'modulos' => $entitlements['modulos'],
            'em_trial' => $entitlements['em_trial'],
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Support\InstituicaoSubscriptionGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PlatformInstituicaoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'nome_fantasia', type: 'string'),
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', nullable: true),
        new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'trial_usa_padrao', type: 'boolean'),
        new OA\Property(property: 'assinatura_status', type: 'string'),
        new OA\Property(property: 'em_trial', type: 'boolean'),
        new OA\Property(property: 'trial_expirado', type: 'boolean'),
        new OA\Property(property: 'acesso_bloqueado', type: 'boolean'),
    ]
)]
class PlatformInstituicaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guard = app(InstituicaoSubscriptionGuard::class);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome_fantasia' => $this->nome_fantasia,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'rua' => $this->rua,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'complemento' => $this->complemento,
            'cep' => $this->cep,
            'id_estado' => $this->id_estado,
            'id_cidade' => $this->id_cidade,
            'status' => $this->status,
            'id_plano_assinatura' => $this->id_plano_assinatura,
            'plano' => $this->whenLoaded('planoAssinatura', fn () => new PlatformPlanoAssinaturaResource($this->planoAssinatura)),
            'trial_ends_at' => $this->trial_ends_at,
            'trial_usa_padrao' => $this->trial_usa_padrao,
            'assinatura_inicia_em' => $this->assinatura_inicia_em,
            'assinatura_status' => $this->assinatura_status,
            'em_trial' => $guard->emTrial($this->resource),
            'trial_expirado' => $guard->trialExpirado($this->resource),
            'acesso_bloqueado' => $guard->isBlocked($this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}

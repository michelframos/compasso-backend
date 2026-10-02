<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformInstituicaoUsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'role' => $this->pivot?->role ?? $this->role,
            'membership_status' => $this->pivot?->status,
            'is_super_admin' => (bool) $this->is_super_admin,
        ];
    }
}

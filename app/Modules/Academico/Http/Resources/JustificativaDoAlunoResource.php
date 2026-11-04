<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\JustificativaFalta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'JustificativaDoAlunoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'id_aula_turma', type: 'integer'),
        new OA\Property(property: 'tipo', type: 'string', enum: JustificativaFalta::TIPOS),
        new OA\Property(property: 'motivo', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: JustificativaFalta::STATUS),
        new OA\Property(property: 'anexo_url', type: 'string', nullable: true),
        new OA\Property(property: 'decidido_em', type: 'string', nullable: true),
    ]
)]
class JustificativaDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_aula_turma' => $this->id_aula_turma,
            'tipo' => $this->tipo,
            'motivo' => $this->motivo,
            'status' => $this->status,
            'anexo_url' => $this->anexo ? Storage::disk('public')->url($this->anexo) : null,
            'decidido_em' => $this->decidido_em ? substr((string) $this->decidido_em, 0, 16) : null,
        ];
    }
}

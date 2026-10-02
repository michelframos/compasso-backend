<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Pessoas\Http\Resources\AlunoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AulaPresencaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mapDbToFrontend = [
            'presente' => 'presente',
            'ausente' => 'falta',
            'justificado' => 'falta_justificada',
        ];

        return [
            'id' => $this->id,
            'id_aula_turma' => $this->id_aula_turma,
            'id_aluno' => $this->id_aluno,
            'status' => $mapDbToFrontend[$this->status] ?? $this->status,
            'observacao' => $this->observacao,
            'aluno' => new AlunoResource($this->whenLoaded('aluno')),
        ];
    }
}

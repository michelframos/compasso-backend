<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatriculaHistoricoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'id_matricula' => $this->id_matricula,
            'id_turma_origem' => $this->id_turma_origem,
            'turma_origem' => $this->turmaOrigem ? [
                'id' => $this->turmaOrigem->id,
                'nome' => ($this->turmaOrigem->curso->nome ?? '') . ' - ' . ($this->turmaOrigem->nivel->nome ?? ''),
            ] : null,
            'id_turma_destino' => $this->id_turma_destino,
            'turma_destino' => [
                'id' => $this->turmaDestino->id,
                'nome' => ($this->turmaDestino->curso->nome ?? '') . ' - ' . ($this->turmaDestino->nivel->nome ?? ''),
            ],
            'data_transferencia' => $this->data_transferencia,
            'motivo' => $this->motivo,
            'created_at' => $this->created_at,
        ];
    }
}

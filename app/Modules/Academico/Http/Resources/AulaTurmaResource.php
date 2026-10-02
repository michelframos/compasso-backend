<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Academico\Http\Resources\TurmaResource;
use App\Modules\Pessoas\Http\Resources\ProfessorResource;
use App\Modules\Pessoas\Http\Resources\AlunoResource;
use App\Modules\Academico\Http\Resources\CursoResource;
use App\Modules\Academico\Http\Resources\NivelResource;
use App\Modules\Financeiro\Http\Resources\ContaResource;

class AulaTurmaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_turma' => $this->id_turma,
            'id_curso' => $this->id_curso,
            'id_nivel' => $this->id_nivel,
            'id_professor' => $this->id_professor,
            'data' => $this->data ? $this->data->format('Y-m-d') : null,
            'hora_inicio' => $this->hora_inicio,
            'hora_termino' => $this->hora_termino,
            'status' => $this->status,
            'tipo' => $this->tipo,
            'id_aluno_especifico' => $this->id_aluno_especifico,
            'conteudo_dado' => $this->conteudo_dado,
            'valor_hora_aula_aplicado' => $this->valor_hora_aula_aplicado,
            'percentual_comissao_aplicado' => $this->percentual_comissao_aplicado,
            'valor_mensalidade_aplicado' => $this->valor_mensalidade_aplicado,
            'turma' => new TurmaResource($this->whenLoaded('turma')),
            'professor' => new ProfessorResource($this->whenLoaded('professor')),
            'aluno_especifico' => new AlunoResource($this->whenLoaded('aluno_especifico')),
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'nivel' => new NivelResource($this->whenLoaded('nivel')),
            'id_conta' => $this->conta?->id,
            'conta' => new ContaResource($this->whenLoaded('conta')),
            'notificar' => (bool) $this->notificar,
        ];
    }
}

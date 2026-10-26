<?php

namespace App\Modules\Academico\Http\Requests\Reposicao;

use App\Modules\Academico\Models\SolicitacaoReposicao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DecidirReposicaoRequest',
    required: ['decisao'],
    properties: [
        new OA\Property(property: 'decisao', type: 'string', enum: ['aprovada', 'recusada']),
        new OA\Property(property: 'id_aula_turma_destino', type: 'integer', nullable: true, description: 'Obrigatório para aprovar quando o aluno não escolheu a aula'),
    ]
)]
class DecidirReposicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decisao' => ['required', Rule::in([SolicitacaoReposicao::STATUS_APROVADA, SolicitacaoReposicao::STATUS_RECUSADA])],
            'id_aula_turma_destino' => ['nullable', 'integer'],
        ];
    }
}

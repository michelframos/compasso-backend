<?php

namespace App\Modules\Core\Http\Requests\Instituicao;

use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdatePermissoesProfessorRequest',
    required: ['criar', 'editar', 'excluir'],
    properties: [
        new OA\Property(property: 'criar', type: 'string', enum: PermissoesProfessorAulas::MODOS, example: 'livre'),
        new OA\Property(property: 'editar', type: 'string', enum: PermissoesProfessorAulas::MODOS, example: 'aprovacao', description: 'Remarcar (reposição) e trocar o professor (substituição)'),
        new OA\Property(property: 'excluir', type: 'string', enum: PermissoesProfessorAulas::MODOS, example: 'aprovacao', description: 'Cancelar ou excluir aulas'),
    ]
)]
class UpdatePermissoesProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return collect(PermissoesProfessorAulas::ACOES)
            ->mapWithKeys(fn (string $acao) => [$acao => ['required', Rule::in(PermissoesProfessorAulas::MODOS)]])
            ->all();
    }
}

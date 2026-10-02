<?php

namespace App\Modules\Academico\Http\Requests\Aula;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ConcluirAulaRequest',
    title: 'Concluir Aula Request',
    description: 'Chamada e conteúdo ministrado para concluir a aula',
    type: 'object',
    required: ['presencas'],
    properties: [
        new OA\Property(property: 'conteudo_dado', type: 'string', nullable: true, example: 'Escala de Dó maior e leitura rítmica'),
        new OA\Property(
            property: 'presencas',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id_aluno', type: 'integer', example: 1),
                new OA\Property(property: 'status', type: 'string', enum: AulaPresenca::STATUS, example: 'presente'),
                new OA\Property(property: 'observacao', type: 'string', nullable: true, example: 'Chegou 15 minutos atrasado'),
            ])
        ),
    ]
)]
class ConcluirAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conteudo_dado' => ['nullable', 'string', 'max:5000'],
            'presencas' => ['present', 'array'],
            'presencas.*.id_aluno' => ['required', 'integer', 'distinct', InstituicaoContext::existsRule('alunos')],
            'presencas.*.status' => ['required', Rule::in(AulaPresenca::STATUS)],
            'presencas.*.observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

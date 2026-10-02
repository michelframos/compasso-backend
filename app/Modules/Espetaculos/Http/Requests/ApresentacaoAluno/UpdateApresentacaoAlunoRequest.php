<?php

namespace App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "UpdateApresentacaoAlunoRequest",
    description: "Request para atualização de Apresentação Aluno",
    type: "object",
    properties: [
        new OA\Property(property: "tamanho_figurino", type: "string", maxLength: 10, nullable: true),
        new OA\Property(property: "valor_figurino", type: "number", format: "float", nullable: true),
        new OA\Property(property: "pago_figurino", type: "boolean"),
        new OA\Property(property: "presenca_ensaio_geral", type: "boolean"),
        new OA\Property(property: "presenca_espetaculo", type: "boolean"),
        new OA\Property(property: "recebeu_figurino", type: "boolean"),
    ]
)]
class UpdateApresentacaoAlunoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tamanho_figurino' => 'nullable|string|max:10',
            'valor_figurino' => 'nullable|numeric|min:0',
            'pago_figurino' => 'boolean',
            'presenca_ensaio_geral' => 'boolean',
            'presenca_espetaculo' => 'boolean',
            'recebeu_figurino' => 'boolean',
        ];
    }
}

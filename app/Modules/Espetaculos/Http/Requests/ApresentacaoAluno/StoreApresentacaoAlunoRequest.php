<?php

namespace App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "StoreApresentacaoAlunoRequest",
    description: "Request para criação de Apresentação Aluno",
    type: "object",
    required: ["id_apresentacao", "id_aluno"],
    properties: [
        new OA\Property(property: "id_apresentacao", type: "integer"),
        new OA\Property(property: "id_aluno", type: "integer"),
        new OA\Property(property: "tamanho_figurino", type: "string", maxLength: 10, nullable: true),
        new OA\Property(property: "valor_figurino", type: "number", format: "float", nullable: true),
        new OA\Property(property: "pago_figurino", type: "boolean", default: false),
        new OA\Property(property: "presenca_ensaio_geral", type: "boolean", default: false),
        new OA\Property(property: "presenca_espetaculo", type: "boolean", default: false),
        new OA\Property(property: "recebeu_figurino", type: "boolean", default: false),
    ]
)]
class StoreApresentacaoAlunoRequest extends FormRequest
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
            'id_apresentacao' => ['required', InstituicaoContext::existsRule('apresentacoes')],
            'id_aluno' => ['required', InstituicaoContext::existsRule('alunos')],
            'tamanho_figurino' => 'nullable|string|max:10',
            'valor_figurino' => 'nullable|numeric|min:0',
            'pago_figurino' => 'boolean',
            'presenca_ensaio_geral' => 'boolean',
            'presenca_espetaculo' => 'boolean',
            'recebeu_figurino' => 'boolean',
        ];
    }
}

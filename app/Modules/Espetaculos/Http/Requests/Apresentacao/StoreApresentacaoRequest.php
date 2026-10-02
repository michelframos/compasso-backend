<?php

namespace App\Modules\Espetaculos\Http\Requests\Apresentacao;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreApresentacaoRequest",
    title: "Store Apresentacao Request",
    description: "Parâmetros para criação de uma apresentação",
    required: ["id_espetaculo"],
    properties: [
        new OA\Property(property: "id_espetaculo", type: "integer", example: 1, description: "ID do Espetáculo (não pode estar concluído ou cancelado)"),
        new OA\Property(property: "id_turma", type: "integer", example: 1, nullable: true),
        new OA\Property(property: "titulo_musica", type: "string", maxLength: 255, example: "Sinfonia No. 5", nullable: true),
        new OA\Property(property: "ordem_entrada", type: "integer", example: 1, nullable: true),
        new OA\Property(property: "duracao_estimada", type: "string", example: "00:05:30", nullable: true)
    ]
)]
class StoreApresentacaoRequest extends FormRequest
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
            'id_espetaculo' => [
                'required',
                'integer',
                InstituicaoContext::existsRule('espetaculos')->whereNotIn('status', ['concluido', 'cancelado']),
            ],
            'id_turma' => ['nullable', 'integer', InstituicaoContext::existsRule('turmas')],
            'titulo_musica' => ['nullable', 'string', 'max:255'],
            'ordem_entrada' => ['nullable', 'integer'],
            'duracao_estimada' => ['nullable', 'date_format:H:i:s'],
        ];
    }

    public function messages()
    {
        return [
            'id_espetaculo.exists' => 'O espetáculo selecionado não existe ou não está disponível para receber novas apresentações (pode estar concluído ou cancelado).',
        ];
    }
}

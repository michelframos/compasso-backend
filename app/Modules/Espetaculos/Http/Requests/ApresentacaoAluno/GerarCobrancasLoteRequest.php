<?php

namespace App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "GerarCobrancasLoteRequest",
    description: "Request para gerar cobranças de figurino em lote",
    type: "object",
    required: ["participantes_ids", "data_vencimento"],
    properties: [
        new OA\Property(
            property: "participantes_ids",
            type: "array",
            items: new OA\Items(type: "integer"),
            description: "IDs dos participantes (apresentacoes_alunos)"
        ),
        new OA\Property(
            property: "data_vencimento",
            type: "string",
            format: "date",
            description: "Data de vencimento das faturas"
        )
    ]
)]
class GerarCobrancasLoteRequest extends FormRequest
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
            'participantes_ids' => 'required|array|min:1',
            'participantes_ids.*' => ['required', 'integer', InstituicaoContext::existsRule('apresentacoes_alunos')],
            'data_vencimento' => 'required|date'
        ];
    }
}

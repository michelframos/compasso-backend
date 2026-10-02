<?php

namespace App\Modules\Financeiro\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreContratoRequest",
    required: ["nome", "conteudo"],
    properties: [
        new OA\Property(property: "nome", type: "string", maxLength: 150, example: "Contrato de Prestação de Serviços"),
        new OA\Property(property: "conteudo", type: "string", example: "Conteúdo do contrato com {{nome_aluno}}...")
    ]
)]
class StoreContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'conteudo' => ['required', 'string'],
        ];
    }
}

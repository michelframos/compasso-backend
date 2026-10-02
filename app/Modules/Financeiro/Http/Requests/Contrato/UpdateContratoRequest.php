<?php

namespace App\Modules\Financeiro\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateContratoRequest",
    properties: [
        new OA\Property(property: "nome", type: "string", maxLength: 150, example: "Contrato Atualizado"),
        new OA\Property(property: "conteudo", type: "string", example: "Conteúdo atualizado...")
    ]
)]
class UpdateContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:150'],
            'conteudo' => ['sometimes', 'string'],
        ];
    }
}

<?php

namespace App\Modules\Financeiro\Http\Requests\CategoriaConta;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateCategoriaContaRequest",
    title: "Update Categoria Conta Request",
    description: "Request para atualização de uma Categoria de Conta",
    type: "object",
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome da categoria", example: "Energia Elétrica"),
        new OA\Property(property: "tipo", type: "string", enum: ["receita", "despesa"], description: "Tipo da categoria (receita ou despesa)"),
        new OA\Property(property: "descricao", type: "string", description: "Descrição detalhada (opcional)", example: "Conta de luz mensal", nullable: true)
    ]
)]
class UpdateCategoriaContaRequest extends FormRequest
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
            'nome' => ['sometimes', 'required', 'string', 'max:100'],
            'tipo' => ['sometimes', 'required', 'in:receita,despesa'],
            'descricao' => ['nullable', 'string'],
        ];
    }
}

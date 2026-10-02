<?php

namespace App\Modules\Financeiro\Http\Requests\CategoriaConta;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreCategoriaContaRequest",
    title: "Store Categoria Conta Request",
    description: "Request para criação de uma Categoria de Conta",
    type: "object",
    required: ["nome", "tipo"],
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome da categoria", example: "Internet"),
        new OA\Property(property: "tipo", type: "string", enum: ["receita", "despesa"], description: "Tipo da categoria (receita ou despesa)"),
        new OA\Property(property: "descricao", type: "string", description: "Descrição detalhada (opcional)", example: "Pagamento mensal do provedor", nullable: true)
    ]
)]
class StoreCategoriaContaRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:receita,despesa'],
            'descricao' => ['nullable', 'string'],
        ];
    }
}

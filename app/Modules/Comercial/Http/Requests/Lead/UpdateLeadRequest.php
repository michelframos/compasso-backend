<?php

namespace App\Modules\Comercial\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateLeadRequest",
    title: "Update Lead Request",
    description: "Parâmetros para atualização de um lead",
    properties: [
        new OA\Property(property: "nome", type: "string", maxLength: 100, example: "João da Silva Sauro", nullable: true),
        new OA\Property(property: "email", type: "string", format: "email", maxLength: 100, example: "joao_novo@example.com", nullable: true),
        new OA\Property(property: "telefone", type: "string", maxLength: 20, example: "(11) 90000-0000", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["novo", "contatado", "matriculado", "perdido"], example: "contatado", nullable: true),
        new OA\Property(property: "observacoes", type: "string", example: "Cliente contatado via WhatsApp e demonstrou interesse.", nullable: true)
    ]
)]
class UpdateLeadRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:100'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:novo,contatado,matriculado,perdido'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}

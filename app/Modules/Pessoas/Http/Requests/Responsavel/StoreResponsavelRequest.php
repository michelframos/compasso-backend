<?php

namespace App\Modules\Pessoas\Http\Requests\Responsavel;

use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Store Responsavel Request",
    description: "Store Responsavel request body data",
    type: "object",
    required: ["nome", "email", "password", "cpf"],
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome do responsável", example: "Carlos Oliveira"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do responsável", example: "carlos@email.com"),
        new OA\Property(property: "password", type: "string", format: "password", description: "Senha", example: "password"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", description: "Confirmação da senha", example: "password"),
        new OA\Property(property: "cpf", type: "string", description: "CPF", example: "123.456.789-00"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações", example: "Pai do aluno X")
    ]
)]
class StoreResponsavelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueEmailInInstituicao()],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'cpf' => ['required', 'string', new \App\Rules\CpfRule, 'unique:usuarios'],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Modules\Pessoas\Http\Requests\Responsavel;

use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Update Responsavel Request",
    description: "Update Responsavel request body data",
    type: "object",
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome do responsável", example: "Carlos Oliveira"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do responsável", example: "carlos@email.com"),
        new OA\Property(property: "password", type: "string", format: "password", description: "Nova senha (opcional)", example: "newpassword"),
        new OA\Property(property: "cpf", type: "string", description: "CPF", example: "123.456.789-00"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações", example: "Pai do aluno X")
    ]
)]
class UpdateResponsavelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('responsavel')->usuario->id;

        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', new UniqueEmailInInstituicao(ignoreUserId: $userId ? (int) $userId : null)],
            'password' => ['nullable', \Illuminate\Validation\Rules\Password::defaults()],
            'cpf' => ['sometimes', 'string', new \App\Rules\CpfRule, 'unique:usuarios,cpf,' . $userId],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Requests\User;

use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Store User Request",
    description: "Store User request body data",
    type: "object",
    required: ["nome", "email", "password", "cpf", "role"],
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome do usuÃ¡rio", example: "JoÃ£o Silva"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do usuÃ¡rio", example: "joao@email.com"),
        new OA\Property(property: "password", type: "string", format: "password", description: "Senha do usuÃ¡rio", example: "password"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", description: "ConfirmaÃ§Ã£o da senha", example: "password"),
        new OA\Property(property: "cpf", type: "string", description: "CPF do usuÃ¡rio", example: "12345678901"),
        new OA\Property(property: "role", type: "string", description: "Role do usuÃ¡rio", example: "admin"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 99999-9999")
    ]
)]
class StoreUserRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueEmailInInstituicao()],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'cpf' => ['required', 'string', new \App\Rules\CpfRule, 'unique:usuarios'],
            'role' => ['required', 'in:admin,secretaria,professor,aluno,responsavel'],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail informado é inválido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.unique' => 'Este CPF já está cadastrado no sistema.',
            'role.required' => 'A função é obrigatória.',
            'role.in' => 'A função selecionada é inválida.',
        ];
    }
}

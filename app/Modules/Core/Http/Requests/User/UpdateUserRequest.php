<?php

namespace App\Modules\Core\Http\Requests\User;

use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Update User Request",
    description: "Update User request body data",
    type: "object",
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
class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user');

        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', new UniqueEmailInInstituicao(ignoreUserId: (int) $userId)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'cpf' => ['sometimes', 'string', new \App\Rules\CpfRule, 'unique:usuarios,cpf,' . $userId],
            'role' => ['sometimes', 'in:admin,secretaria,professor,aluno,responsavel'],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.email' => 'O e-mail informado é inválido.',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'cpf.unique' => 'Este CPF já está cadastrado no sistema.',
            'role.in' => 'A função selecionada é inválida.',
        ];
    }
}

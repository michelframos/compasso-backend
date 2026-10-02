<?php

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlterarSenhaRequest',
    title: 'Alterar Senha Request',
    description: 'Troca da senha do usuário autenticado',
    required: ['senha_atual', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'senha_atual', type: 'string', format: 'password', example: 'SenhaAtual123'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NovaSenha123'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NovaSenha123'),
    ]
)]
class AlterarSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'senha_atual' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', 'different:senha_atual', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'senha_atual.required' => 'Informe a senha atual.',
            'senha_atual.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
            'password.different' => 'A nova senha deve ser diferente da senha atual.',
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ResetPasswordRequest",
    title: "Reset Password Request",
    description: "Request para alterar a senha com o token",
    required: ["email", "token", "password", "password_confirmation"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "joao@email.com"),
        new OA\Property(property: "token", type: "string", example: "123456"),
        new OA\Property(property: "password", type: "string", format: "password", example: "NovaSenha123"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "NovaSenha123"),
    ]
)]
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:usuarios,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O campo e-mail Ã© obrigatÃ³rio.',
            'email.email' => 'O e-mail deve ser um endereÃ§o vÃ¡lido.',
            'email.exists' => 'NÃ£o encontramos nenhum usuÃ¡rio com este endereÃ§o de e-mail.',
            'token.required' => 'O token/cÃ³digo de seguranÃ§a Ã© obrigatÃ³rio.',
            'password.required' => 'A senha Ã© obrigatÃ³ria.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmaÃ§Ã£o da senha nÃ£o confere.',
        ];
    }
}

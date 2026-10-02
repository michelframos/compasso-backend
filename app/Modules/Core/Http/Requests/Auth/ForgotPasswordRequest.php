<?php

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ForgotPasswordRequest",
    title: "Forgot Password Request",
    description: "Request para enviar cÃ³digo de recuperaÃ§Ã£o de senha",
    required: ["email"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "joao@email.com")
    ]
)]
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:usuarios,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O campo e-mail Ã© obrigatÃ³rio.',
            'email.email' => 'O e-mail deve ser um endereÃ§o vÃ¡lido.',
            'email.exists' => 'NÃ£o encontramos nenhum usuÃ¡rio com este endereÃ§o de e-mail.',
        ];
    }
}

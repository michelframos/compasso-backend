<?php

namespace App\Modules\Core\Http\Requests\Auth;

use App\Modules\Core\Models\Instituicao;
use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Register Request",
    description: "Register request body data",
    required: ["nome", "email", "password", "cpf", "role"],
    properties: [
        new OA\Property(property: "nome", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password"),
        new OA\Property(property: "cpf", type: "string", example: "123.456.789-00"),
        new OA\Property(property: "role", type: "string", enum: ["admin", "professor", "aluno", "responsavel"], example: "aluno"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 98765-4321"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 98765-4321")
    ]
)]
class RegisterRequest extends FormRequest
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
        $defaultInstituicaoId = Instituicao::query()->where('slug', 'default')->value('id');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new UniqueEmailInInstituicao(instituicaoId: $defaultInstituicaoId ? (int) $defaultInstituicaoId : null)],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'cpf' => ['required', 'string', new \App\Rules\CpfRule, 'unique:usuarios'],
            'role' => ['required', 'in:admin,professor,aluno,responsavel'],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
        ];
    }
}

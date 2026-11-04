<?php

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Aluno Login Request',
    description: 'Login da área do aluno/responsável. Informe apenas e-mail e senha; a escola é resolvida automaticamente. Se houver mais de uma, envie tenant_slug na segunda tentativa.',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana.aluno@escola.test'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SenhaAtual123'),
        new OA\Property(property: 'tenant_slug', type: 'string', nullable: true, example: 'escola-michel'),
    ]
)]
class AlunoLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['nullable', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Modules\Pessoas\Http\Requests\Professor;

use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Update Professor Request",
    description: "Update Professor request body data",
    type: "object",
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome do professor", example: "João Silva"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do professor", example: "joao@email.com"),
        new OA\Property(property: "password", type: "string", format: "password", description: "Senha do professor", example: "password"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", description: "Confirmação da senha", example: "password"),
        new OA\Property(property: "cpf", type: "string", description: "CPF do professor", example: "12345678901"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "comissao", type: "number", format: "float", nullable: true, description: "Percentual de comissão do professor", example: 15.5),
        new OA\Property(property: "salario_fixo", type: "number", format: "float", nullable: true, description: "Salário fixo do professor", example: 3000.00),
        new OA\Property(property: "valor_hora_aula", type: "number", format: "float", nullable: true, description: "Valor por hora/aula ministrada", example: 50.00),
        new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações sobre o professor", example: "Especialista em violino"),
    ]
)]
class UpdateProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $professor = $this->route('professor');
        $userId = $professor ? $professor->id_usuario : null;

        return [
            'nome'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'string', 'email', 'max:255', new UniqueEmailInInstituicao(ignoreUserId: $userId ? (int) $userId : null)],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'cpf'      => ['sometimes', 'string', new \App\Rules\CpfRule, 'unique:usuarios,cpf,' . $userId],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'salario_fixo' => ['nullable', 'numeric', 'min:0'],
            'valor_hora_aula' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}

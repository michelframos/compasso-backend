<?php

namespace App\Modules\Pessoas\Http\Requests\Aluno;

use App\Modules\Core\Support\InstituicaoContext;
use App\Rules\UniqueEmailInInstituicao;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Store Aluno Request",
    description: "Store Aluno request body data",
    type: "object",
    required: ["nome"],
    properties: [
        new OA\Property(property: "nome", type: "string", description: "Nome do aluno", example: "Maria Souza"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do aluno", example: "maria@email.com"),
        new OA\Property(property: "password", type: "string", format: "password", description: "Senha do aluno", example: "password"),
        new OA\Property(property: "password_confirmation", type: "string", format: "password", description: "Confirmação da senha", example: "password"),
        new OA\Property(property: "cpf", type: "string", description: "CPF do aluno", example: "12345678901"),
        new OA\Property(property: "telefone", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "whatsapp", type: "string", nullable: true, example: "(11) 99999-9999"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações sobre o aluno", example: "Aluno iniciante"),
        new OA\Property(property: "data_nascimento", type: "string", format: "date", description: "Data de nascimento do aluno", example: "2010-01-01"),
        new OA\Property(property: "id_responsavel", type: "integer", description: "ID do responsável (opcional para vínculo existente)", nullable: true, example: 1),
        new OA\Property(property: "responsavel_nome", type: "string", description: "Nome do responsável", nullable: true, example: "José Souza"),
        new OA\Property(property: "responsavel_email", type: "string", format: "email", description: "Email do responsável", nullable: true, example: "jose@email.com"),
        new OA\Property(property: "responsavel_cpf", type: "string", description: "CPF do responsável", nullable: true, example: "98765432100"),
        new OA\Property(property: "responsavel_telefone", type: "string", nullable: true, example: "(11) 98888-8888"),
        new OA\Property(property: "responsavel_whatsapp", type: "string", nullable: true, example: "(11) 98888-8888"),
        new OA\Property(property: "responsavel_observacoes", type: "string", nullable: true, description: "Observações sobre o responsável", example: "Pai do aluno")
    ]
)]
class StoreAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', new UniqueEmailInInstituicao()],
            'data_nascimento' => ['nullable', 'date'],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'cpf' => ['nullable', 'string', new \App\Rules\CpfRule, 'unique:usuarios'],
            'telefone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
            'id_lead' => ['nullable', 'exists:leads,id'],

            // Endereço do Aluno
            'cep' => ['nullable', 'string', 'max:10'],
            'rua' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'id_estado' => ['nullable', 'exists:estados,id'],
            'id_cidade' => ['nullable', 'exists:cidades,id'],

            // Campos do Responsável
            'id_responsavel' => ['nullable', 'integer', InstituicaoContext::existsRule('responsaveis')],
            'responsavel_nome' => ['nullable', 'string', 'max:255'],
            'responsavel_email' => ['nullable', 'string', 'email', 'max:255'],
            'responsavel_cpf' => ['nullable', 'string', new \App\Rules\CpfRule],
            'responsavel_telefone' => ['nullable', 'string'],
            'responsavel_whatsapp' => ['nullable', 'string'],
            'responsavel_observacoes' => ['nullable', 'string'],
            
            // Endereço do Responsável
            'responsavel_cep' => ['nullable', 'string', 'max:10'],
            'responsavel_rua' => ['nullable', 'string', 'max:255'],
            'responsavel_numero' => ['nullable', 'string', 'max:20'],
            'responsavel_complemento' => ['nullable', 'string', 'max:100'],
            'responsavel_bairro' => ['nullable', 'string', 'max:255'],
            'responsavel_id_estado' => ['nullable', 'exists:estados,id'],
            'responsavel_id_cidade' => ['nullable', 'exists:cidades,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do aluno é obrigatório.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.email' => 'O e-mail informado é inválido.',
            'email.unique' => 'Este e-mail já está em uso por outro usuário.',
            'data_nascimento.date' => 'A data de nascimento informada é inválida.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'cpf.unique' => 'Este CPF já está cadastrado no sistema.',
            'id_lead.exists' => 'O lead selecionado não foi encontrado.',
            'id_responsavel.exists' => 'O responsável selecionado não foi encontrado.',
            'responsavel_nome.string' => 'O nome do responsável deve ser um texto.',
            'responsavel_email.email' => 'O e-mail do responsável é inválido.',
        ];
    }
}

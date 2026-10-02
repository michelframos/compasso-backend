<?php

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdatePerfilRequest',
    title: 'Update Perfil Request',
    description: 'Dados pessoais editáveis pelo próprio usuário. E-mail, CPF e papel são mantidos pela secretaria.',
    required: ['nome'],
    properties: [
        new OA\Property(property: 'nome', type: 'string', example: 'Maria Souza'),
        new OA\Property(property: 'telefone', type: 'string', nullable: true, example: '(11) 3333-4444'),
        new OA\Property(property: 'whatsapp', type: 'string', nullable: true, example: '(11) 99999-8888'),
        new OA\Property(property: 'data_aniversario', type: 'string', format: 'date', nullable: true, example: '1990-05-20'),
        new OA\Property(property: 'cep', type: 'string', nullable: true, example: '01001-000'),
        new OA\Property(property: 'rua', type: 'string', nullable: true, example: 'Praça da Sé'),
        new OA\Property(property: 'numero', type: 'string', nullable: true, example: '100'),
        new OA\Property(property: 'complemento', type: 'string', nullable: true, example: 'Apto 12'),
        new OA\Property(property: 'bairro', type: 'string', nullable: true, example: 'Sé'),
        new OA\Property(property: 'id_estado', type: 'integer', nullable: true, example: 25),
        new OA\Property(property: 'id_cidade', type: 'integer', nullable: true, example: 5270),
    ]
)]
class UpdatePerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'data_aniversario' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'cep' => ['nullable', 'string', 'max:10'],
            'rua' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'id_estado' => ['nullable', 'integer', 'exists:estados,id'],
            'id_cidade' => [
                'nullable',
                'integer',
                'required_with:id_estado',
                Rule::exists('cidades', 'id')->where('id_estado', $this->input('id_estado')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o seu nome.',
            'data_aniversario.before' => 'A data de nascimento deve ser anterior a hoje.',
            'id_cidade.required_with' => 'Selecione a cidade.',
            'id_cidade.exists' => 'A cidade não pertence ao estado selecionado.',
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StorePlatformPlanoAssinaturaRequest',
    required: ['slug', 'nome', 'preco_mensal'],
    properties: [
        new OA\Property(property: 'slug', type: 'string', example: 'profissional'),
        new OA\Property(property: 'nome', type: 'string', example: 'Profissional'),
        new OA\Property(property: 'descricao', type: 'string', nullable: true),
        new OA\Property(property: 'preco_mensal', type: 'number', format: 'float', example: 199.9),
        new OA\Property(property: 'limite_alunos', type: 'integer', nullable: true, example: 500),
        new OA\Property(
            property: 'modulos',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['leads', 'financeiro']
        ),
        new OA\Property(property: 'ativo', type: 'boolean', example: true),
    ]
)]
class StorePlatformPlanoAssinaturaRequest extends FormRequest
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
        $modulosKeys = array_keys(config('platform.modulos_app', []));

        return [
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:planos_assinatura,slug'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco_mensal' => ['required', 'numeric', 'min:0'],
            'limite_alunos' => ['nullable', 'integer', 'min:1'],
            'modulos' => ['nullable', 'array'],
            'modulos.*' => ['string', 'in:'.implode(',', $modulosKeys)],
            'ativo' => ['nullable', 'boolean'],
        ];
    }
}

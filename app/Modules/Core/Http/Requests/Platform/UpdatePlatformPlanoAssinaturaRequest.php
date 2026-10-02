<?php

namespace App\Modules\Core\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformPlanoAssinaturaRequest extends FormRequest
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
        $planoId = $this->route('plano');

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('planos_assinatura', 'slug')->ignore($planoId),
            ],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco_mensal' => ['sometimes', 'required', 'numeric', 'min:0'],
            'limite_alunos' => ['nullable', 'integer', 'min:1'],
            'modulos' => ['sometimes', 'nullable', 'array'],
            'modulos.*' => ['string', 'in:'.implode(',', array_keys(config('platform.modulos_app', [])))],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}

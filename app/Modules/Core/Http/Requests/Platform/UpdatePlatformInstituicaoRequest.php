<?php

namespace App\Modules\Core\Http\Requests\Platform;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Models\Instituicao;
use App\Rules\CnpjRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformInstituicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cnpj')) {
            $this->merge(['cnpj' => Cnpj::normalize($this->input('cnpj'))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $instituicaoId = $this->route('instituicao');

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('instituicoes', 'slug')->ignore($instituicaoId),
            ],
            'nome_fantasia' => ['sometimes', 'required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'sometimes',
                'required',
                'string',
                new CnpjRule,
                Rule::unique('instituicoes', 'cnpj')->ignore($instituicaoId),
            ],
            'rua' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:10'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:10'],
            'id_estado' => ['nullable', 'integer', 'exists:estados,id'],
            'id_cidade' => ['nullable', 'integer', 'exists:cidades,id'],
            'status' => ['sometimes', Rule::in([
                Instituicao::STATUS_ATIVO,
                Instituicao::STATUS_INATIVO,
                Instituicao::STATUS_SUSPENSO,
            ])],
            'id_plano_assinatura' => ['nullable', 'integer', 'exists:planos_assinatura,id'],
            'trial_ends_at' => ['nullable', 'date'],
            'trial_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'usar_trial_padrao' => ['nullable', 'boolean'],
            'assinatura_inicia_em' => ['nullable', 'date'],
            'assinatura_status' => ['sometimes', Rule::in([
                Instituicao::ASSINATURA_TRIALING,
                Instituicao::ASSINATURA_ACTIVE,
                Instituicao::ASSINATURA_PAST_DUE,
                Instituicao::ASSINATURA_CANCELED,
            ])],
        ];
    }
}

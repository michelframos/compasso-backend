<?php

namespace App\Modules\Core\Http\Requests\Platform;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Models\Instituicao;
use App\Rules\CnpjRule;
use App\Rules\CpfRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StorePlatformInstituicaoRequest',
    required: ['slug', 'nome_fantasia', 'cnpj', 'admin_nome', 'admin_email', 'admin_password'],
    properties: [
        new OA\Property(property: 'slug', type: 'string', example: 'escola-exemplo'),
        new OA\Property(property: 'nome_fantasia', type: 'string', example: 'Escola Exemplo'),
        new OA\Property(property: 'razao_social', type: 'string', nullable: true),
        new OA\Property(property: 'cnpj', type: 'string', example: '11.222.333/0001-81'),
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', nullable: true),
        new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'trial_dias', type: 'integer', nullable: true, example: 30),
        new OA\Property(property: 'usar_trial_padrao', type: 'boolean', nullable: true),
        new OA\Property(property: 'assinatura_status', type: 'string', enum: ['trialing', 'active', 'past_due', 'canceled']),
        new OA\Property(property: 'status', type: 'string', enum: ['a', 'i', 's']),
        new OA\Property(property: 'admin_nome', type: 'string', example: 'Maria Silva'),
        new OA\Property(property: 'admin_email', type: 'string', format: 'email', example: 'admin@escola.com'),
        new OA\Property(property: 'admin_password', type: 'string', format: 'password'),
        new OA\Property(property: 'admin_password_confirmation', type: 'string', format: 'password'),
        new OA\Property(property: 'admin_cpf', type: 'string', nullable: true),
    ]
)]
class StorePlatformInstituicaoRequest extends FormRequest
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
        return [
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:instituicoes,slug'],
            'nome_fantasia' => ['required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new CnpjRule, 'unique:instituicoes,cnpj'],
            'rua' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:10'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:10'],
            'id_estado' => ['nullable', 'integer', 'exists:estados,id'],
            'id_cidade' => ['nullable', 'integer', 'exists:cidades,id'],
            'status' => ['nullable', Rule::in([
                Instituicao::STATUS_ATIVO,
                Instituicao::STATUS_INATIVO,
                Instituicao::STATUS_SUSPENSO,
            ])],
            'id_plano_assinatura' => ['nullable', 'integer', 'exists:planos_assinatura,id'],
            'trial_ends_at' => ['nullable', 'date'],
            'trial_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'usar_trial_padrao' => ['nullable', 'boolean'],
            'assinatura_inicia_em' => ['nullable', 'date'],
            'assinatura_status' => ['nullable', Rule::in([
                Instituicao::ASSINATURA_TRIALING,
                Instituicao::ASSINATURA_ACTIVE,
                Instituicao::ASSINATURA_PAST_DUE,
                Instituicao::ASSINATURA_CANCELED,
            ])],
            'admin_nome' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Password::defaults()],
            'admin_cpf' => ['nullable', 'string', new CpfRule, 'unique:usuarios,cpf'],
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Public;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Rules\CnpjRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PublicSignupRequest',
    required: ['slug', 'nome_fantasia', 'cnpj', 'admin_nome', 'admin_email', 'admin_password', 'admin_password_confirmation'],
    properties: [
        new OA\Property(property: 'slug', type: 'string', example: 'escola-harmonia'),
        new OA\Property(property: 'nome_fantasia', type: 'string', example: 'Escola Harmonia'),
        new OA\Property(property: 'cnpj', type: 'string', example: '11.222.333/0001-81'),
        new OA\Property(property: 'admin_nome', type: 'string', example: 'Maria Silva'),
        new OA\Property(property: 'admin_email', type: 'string', format: 'email'),
        new OA\Property(property: 'admin_password', type: 'string', format: 'password'),
        new OA\Property(property: 'admin_password_confirmation', type: 'string', format: 'password'),
        new OA\Property(property: 'plano_slug', type: 'string', nullable: true, example: 'profissional'),
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', nullable: true),
        new OA\Property(property: 'website', type: 'string', nullable: true, description: 'Honeypot — deve ficar vazio'),
    ]
)]
class PublicSignupRequest extends FormRequest
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
            'cnpj' => ['required', 'string', new CnpjRule, 'unique:instituicoes,cnpj'],
            'admin_nome' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Password::defaults()],
            'plano_slug' => ['nullable', 'string', 'max:64', 'exists:planos_assinatura,slug'],
            'id_plano_assinatura' => ['nullable', 'integer', 'exists:planos_assinatura,id'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}

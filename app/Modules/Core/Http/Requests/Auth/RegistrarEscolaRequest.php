<?php

namespace App\Modules\Core\Http\Requests\Auth;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Rules\CnpjRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegistrarEscolaRequest',
    required: ['nome_fantasia', 'cnpj', 'responsavel_nome', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'nome_fantasia', type: 'string', example: 'Escola Harmonia'),
        new OA\Property(property: 'cnpj', type: 'string', example: '11.222.333/0001-81'),
        new OA\Property(property: 'responsavel_nome', type: 'string', example: 'Maria Silva'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string', format: 'password'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
    ]
)]
class RegistrarEscolaRequest extends FormRequest
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
            'nome_fantasia' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new CnpjRule, 'unique:instituicoes,cnpj'],
            'responsavel_nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.unique' => 'Já existe uma escola cadastrada com este CNPJ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome_fantasia' => 'nome fantasia',
            'responsavel_nome' => 'nome do responsável',
            'password' => 'senha',
        ];
    }
}

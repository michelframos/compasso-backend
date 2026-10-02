<?php

namespace App\Modules\Core\Http\Requests\Auth;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class ReenviarCodigoAtivacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_cnpj')) {
            $this->merge(['tenant_cnpj' => Cnpj::normalize($this->input('tenant_cnpj'))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_cnpj' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_cnpj.required' => 'Informe o CNPJ da escola.',
        ];
    }
}

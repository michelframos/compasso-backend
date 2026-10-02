<?php

namespace App\Modules\Core\Http\Requests\ConfiguracaoEmpresa;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Support\InstituicaoContext;
use App\Rules\CnpjRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguracaoEmpresaRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'nome_fantasia' => 'required|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => [
                'required',
                'string',
                new CnpjRule,
                Rule::unique('instituicoes', 'cnpj')->ignore(InstituicaoContext::id()),
            ],
            'rua' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:10',
            'bairro' => 'nullable|string|max:255',
            'complemento' => 'nullable|string|max:255',
            'cep' => 'nullable|string|max:10',
            'id_estado' => 'nullable|exists:estados,id',
            'id_cidade' => 'nullable|exists:cidades,id',
        ];
    }
}

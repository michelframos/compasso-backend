<?php

namespace App\Modules\Bilheteria\Http\Requests;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreCupomIngressoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50'],
            'tipo' => ['required', 'in:percentual,valor_fixo'],
            'valor' => ['required', 'numeric', 'min:0'],
            'id_espetaculo' => ['nullable', InstituicaoContext::existsRule('espetaculos')],
            'ativo' => ['sometimes', 'boolean'],
            'valido_de' => ['nullable', 'date'],
            'valido_ate' => ['nullable', 'date', 'after_or_equal:valido_de'],
            'uso_maximo' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo')) {
            $this->merge(['codigo' => strtoupper(trim((string) $this->input('codigo')))]);
        }
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Platform;

use App\Modules\Core\Support\SiteModuloIcones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSiteModuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recursos = $this->input('recursos');

        if (is_string($recursos)) {
            $linhas = preg_split('/\r\n|\n|\r/', $recursos) ?: [];
            $this->merge([
                'recursos' => array_values(array_filter(array_map('trim', $linhas), fn (string $l) => $l !== '')),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['sometimes', 'required', 'string', 'max:2000'],
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['string', 'max:255'],
            'icone' => ['nullable', 'string', Rule::in(SiteModuloIcones::WHITELIST)],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

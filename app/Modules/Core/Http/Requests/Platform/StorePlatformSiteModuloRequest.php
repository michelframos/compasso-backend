<?php

namespace App\Modules\Core\Http\Requests\Platform;

use App\Modules\Core\Support\SiteModuloIcones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StorePlatformSiteModuloRequest',
    required: ['nome', 'descricao'],
    properties: [
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'descricao', type: 'string'),
        new OA\Property(property: 'recursos', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'icone', type: 'string'),
        new OA\Property(property: 'ordem', type: 'integer'),
    ]
)]
class StorePlatformSiteModuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recursos = $this->input('recursos');

        if (is_string($recursos)) {
            $this->merge([
                'recursos' => $this->linhasParaArray($recursos),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string', 'max:2000'],
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['string', 'max:255'],
            'icone' => ['nullable', 'string', Rule::in(SiteModuloIcones::WHITELIST)],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    private function linhasParaArray(string $texto): array
    {
        $linhas = preg_split('/\r\n|\n|\r/', $texto) ?: [];

        return array_values(array_filter(array_map('trim', $linhas), fn (string $l) => $l !== ''));
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Instituicao;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateModuloInstituicaoRequest',
    required: ['ativo'],
    properties: [
        new OA\Property(property: 'ativo', type: 'boolean', example: false),
    ]
)]
class UpdateModuloInstituicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['modulo' => $this->route('modulo')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'modulo' => ['required', 'string', Rule::in(app(PlanoEntitlementResolverInterface::class)->modulosDesativaveis())],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modulo.in' => 'Este módulo não pode ser ligado ou desligado pela escola.',
        ];
    }
}

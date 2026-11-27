<?php

namespace App\Modules\Core\Http\Requests\Assinatura;

use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateConfiguracaoCobrancaOnlineRequest',
    required: ['meios'],
    properties: [
        new OA\Property(property: 'meios', type: 'array', items: new OA\Items(type: 'string', enum: ['pix', 'credit_card', 'boleto'])),
    ]
)]
class UpdateConfiguracaoCobrancaOnlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meios' => ['required', 'array', 'min:1'],
            'meios.*' => ['required', 'string', 'distinct', Rule::in(Instituicao::MEIOS_COBRANCA_DISPONIVEIS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meios' => 'meios de cobrança',
            'meios.*' => 'meio de cobrança',
        ];
    }
}

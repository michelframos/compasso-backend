<?php

namespace App\Modules\Core\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateDestinoSaqueRequest',
    required: ['pix_key', 'pix_key_type'],
    properties: [
        new OA\Property(property: 'pix_key', type: 'string', example: '12345678000199'),
        new OA\Property(property: 'pix_key_type', type: 'string', enum: ['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'EVP'], example: 'CNPJ'),
    ]
)]
class UpdateDestinoSaqueRequest extends FormRequest
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
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_key_type' => ['required', 'string', Rule::in(['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'EVP'])],
        ];
    }
}

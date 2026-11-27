<?php

namespace App\Modules\Financeiro\Http\Requests\CobrancaOnline;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriarCobrancaOnlineContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['pix', 'credit_card', 'boleto'])],
            'id_aluno' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.in' => 'Meio de pagamento inválido. Use pix, credit_card ou boleto.',
        ];
    }
}

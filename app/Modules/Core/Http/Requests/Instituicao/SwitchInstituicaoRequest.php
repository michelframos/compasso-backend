<?php

namespace App\Modules\Core\Http\Requests\Instituicao;

use Illuminate\Foundation\Http\FormRequest;

class SwitchInstituicaoRequest extends FormRequest
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
            'tenant_slug' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Modules\Core\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformConfiguracoesRequest extends FormRequest
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
            'default_trial_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
            'email_cadastro_assunto' => ['sometimes', 'required', 'string', 'max:255'],
            'email_cadastro_corpo' => ['sometimes', 'required', 'string', 'max:65000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_trial_days' => 'dias de gratuidade',
            'email_cadastro_assunto' => 'assunto do e-mail',
            'email_cadastro_corpo' => 'corpo do e-mail',
        ];
    }
}

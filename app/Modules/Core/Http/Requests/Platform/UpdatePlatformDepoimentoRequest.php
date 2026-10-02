<?php

namespace App\Modules\Core\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformDepoimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('avatar_url') === '') {
            $this->merge(['avatar_url' => null]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'cargo' => ['sometimes', 'required', 'string', 'max:255'],
            'escola' => ['sometimes', 'required', 'string', 'max:255'],
            'conteudo' => ['sometimes', 'required', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'string', 'max:2048', 'url'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

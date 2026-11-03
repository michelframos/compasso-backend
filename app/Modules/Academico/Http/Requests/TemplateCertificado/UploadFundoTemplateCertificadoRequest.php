<?php

namespace App\Modules\Academico\Http\Requests\TemplateCertificado;

use Illuminate\Foundation\Http\FormRequest;

class UploadFundoTemplateCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fundo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'fundo.required' => 'Selecione uma imagem de fundo.',
            'fundo.image' => 'O arquivo precisa ser uma imagem.',
            'fundo.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'fundo.max' => 'A imagem deve ter no máximo 5MB.',
        ];
    }
}

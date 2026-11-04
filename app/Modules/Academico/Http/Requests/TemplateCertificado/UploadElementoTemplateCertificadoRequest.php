<?php

namespace App\Modules\Academico\Http\Requests\TemplateCertificado;

use Illuminate\Foundation\Http\FormRequest;

class UploadElementoTemplateCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'imagem' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'imagem.required' => 'Selecione uma imagem.',
            'imagem.image' => 'O arquivo precisa ser uma imagem.',
            'imagem.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'imagem.max' => 'A imagem deve ter no máximo 5MB.',
        ];
    }
}

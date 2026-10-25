<?php

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AtualizarFotoPerfilRequest',
    title: 'Atualizar Foto de Perfil Request',
    required: ['foto'],
    properties: [
        new OA\Property(property: 'foto', type: 'string', format: 'binary', description: 'Imagem JPG, PNG ou WEBP de até 2MB'),
    ]
)]
class AtualizarFotoPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required' => 'Selecione uma imagem.',
            'foto.image' => 'O arquivo precisa ser uma imagem.',
            'foto.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'foto.max' => 'A imagem deve ter no máximo 2MB.',
        ];
    }
}

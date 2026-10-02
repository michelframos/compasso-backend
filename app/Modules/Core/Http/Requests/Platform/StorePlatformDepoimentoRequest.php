<?php

namespace App\Modules\Core\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StorePlatformDepoimentoRequest',
    required: ['nome', 'cargo', 'escola', 'conteudo'],
    properties: [
        new OA\Property(property: 'nome', type: 'string', example: 'Carla Mendes'),
        new OA\Property(property: 'cargo', type: 'string', example: 'Diretora'),
        new OA\Property(property: 'escola', type: 'string', example: 'Escola Harmonia'),
        new OA\Property(property: 'conteudo', type: 'string'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
        new OA\Property(property: 'ordem', type: 'integer', example: 0),
    ]
)]
class StorePlatformDepoimentoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255'],
            'cargo' => ['required', 'string', 'max:255'],
            'escola' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'string', 'max:2048', 'url'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

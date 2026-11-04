<?php

namespace App\Modules\Academico\Http\Requests\TemplateCertificado;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreTemplateCertificadoRequest',
    required: ['nome', 'conteudo'],
    properties: [
        new OA\Property(property: 'nome', type: 'string', maxLength: 150),
        new OA\Property(property: 'conteudo', type: 'string'),
    ]
)]
class StoreTemplateCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'conteudo' => ['required', 'string'],
        ];
    }
}

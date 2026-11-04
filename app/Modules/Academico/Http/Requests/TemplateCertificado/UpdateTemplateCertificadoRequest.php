<?php

namespace App\Modules\Academico\Http\Requests\TemplateCertificado;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateTemplateCertificadoRequest',
    properties: [
        new OA\Property(property: 'nome', type: 'string', maxLength: 150),
        new OA\Property(property: 'conteudo', type: 'string'),
    ]
)]
class UpdateTemplateCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'conteudo' => ['sometimes', 'required', 'string'],
        ];
    }
}

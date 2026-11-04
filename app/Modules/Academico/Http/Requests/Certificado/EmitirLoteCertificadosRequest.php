<?php

namespace App\Modules\Academico\Http\Requests\Certificado;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmitirLoteCertificadosRequest',
    required: ['matricula_ids'],
    properties: [
        new OA\Property(
            property: 'matricula_ids',
            type: 'array',
            maxItems: 100,
            items: new OA\Items(type: 'integer'),
        ),
    ]
)]
class EmitirLoteCertificadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'matricula_ids' => ['required', 'array', 'min:1', 'max:100'],
            'matricula_ids.*' => ['integer', 'distinct', InstituicaoContext::existsRule('matriculas')],
        ];
    }
}

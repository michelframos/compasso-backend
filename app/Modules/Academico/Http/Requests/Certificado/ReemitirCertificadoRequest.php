<?php

namespace App\Modules\Academico\Http\Requests\Certificado;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReemitirCertificadoRequest',
    properties: [
        new OA\Property(property: 'id_template_certificado', type: 'integer', nullable: true),
    ]
)]
class ReemitirCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_template_certificado' => [
                'nullable',
                'integer',
                InstituicaoContext::existsRule('templates_certificados'),
            ],
        ];
    }
}

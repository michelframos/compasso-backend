<?php

namespace App\Modules\Academico\Http\Requests\JustificativaFalta;

use App\Modules\Academico\Models\JustificativaFalta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DecidirJustificativaRequest',
    required: ['decisao'],
    properties: [
        new OA\Property(property: 'decisao', type: 'string', enum: [JustificativaFalta::STATUS_ACEITA, JustificativaFalta::STATUS_RECUSADA]),
    ]
)]
class DecidirJustificativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decisao' => ['required', Rule::in([JustificativaFalta::STATUS_ACEITA, JustificativaFalta::STATUS_RECUSADA])],
        ];
    }
}

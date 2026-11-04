<?php

namespace App\Modules\Academico\Http\Requests\Certificado;

use Illuminate\Foundation\Http\FormRequest;

class ListCertificadosDoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}

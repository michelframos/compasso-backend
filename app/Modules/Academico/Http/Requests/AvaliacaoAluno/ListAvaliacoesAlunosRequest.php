<?php

namespace App\Modules\Academico\Http\Requests\AvaliacaoAluno;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class ListAvaliacoesAlunosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_turma' => ['required_without:id_aluno', 'nullable', 'integer', InstituicaoContext::existsRule('turmas')],
            'id_aluno' => ['required_without:id_turma', 'nullable', 'integer', InstituicaoContext::existsRule('alunos')],
        ];
    }

    public function messages(): array
    {
        return [
            'id_turma.required_without' => 'Informe a turma ou o aluno.',
            'id_aluno.required_without' => 'Informe a turma ou o aluno.',
        ];
    }
}

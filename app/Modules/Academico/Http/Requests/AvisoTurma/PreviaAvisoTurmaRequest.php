<?php

namespace App\Modules\Academico\Http\Requests\AvisoTurma;

use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Core\Contracts\EnviarAvisoTurmaPort;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alvo, público e canais de um aviso: compartilhado pela prévia e pelo envio. */
class PreviaAvisoTurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_turma' => ['nullable', 'integer', 'required_without:ids_alunos', InstituicaoContext::existsRule('turmas')],
            'ids_alunos' => ['nullable', 'array', 'required_without:id_turma', 'max:500'],
            'ids_alunos.*' => ['integer', 'distinct'],
            'publico' => ['required', Rule::in(AvisoTurma::PUBLICOS)],
            'canais' => ['required', 'array', 'min:1'],
            'canais.*' => ['distinct', Rule::in(EnviarAvisoTurmaPort::CANAIS)],
        ];
    }

    public function messages(): array
    {
        return [
            'id_turma.required_without' => 'Escolha a turma ou os alunos que vão receber o aviso.',
            'ids_alunos.required_without' => 'Escolha a turma ou os alunos que vão receber o aviso.',
            'publico.required' => 'Escolha quem recebe: alunos, responsáveis ou ambos.',
            'canais.required' => 'Escolha ao menos um canal.',
            'canais.min' => 'Escolha ao menos um canal.',
        ];
    }
}

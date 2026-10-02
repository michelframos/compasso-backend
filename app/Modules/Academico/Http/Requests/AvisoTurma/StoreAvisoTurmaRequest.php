<?php

namespace App\Modules\Academico\Http\Requests\AvisoTurma;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAvisoTurmaRequest',
    required: ['titulo', 'mensagem', 'publico', 'canais'],
    properties: [
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Turma inteira (matrículas vigentes); obrigatório sem ids_alunos'),
        new OA\Property(property: 'ids_alunos', type: 'array', nullable: true, items: new OA\Items(type: 'integer'), description: 'Alunos escolhidos (da turma, se informada; senão, alunos do professor)'),
        new OA\Property(property: 'publico', type: 'string', enum: ['alunos', 'responsaveis', 'ambos'], description: 'Aluno sem contato no canal é avisado pelos responsáveis'),
        new OA\Property(property: 'canais', type: 'array', items: new OA\Items(type: 'string', enum: ['email', 'whatsapp'])),
        new OA\Property(property: 'titulo', type: 'string', maxLength: 120, example: 'Ensaio extra no sábado'),
        new OA\Property(property: 'mensagem', type: 'string', maxLength: 2000),
    ]
)]
class StoreAvisoTurmaRequest extends PreviaAvisoTurmaRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'titulo' => ['required', 'string', 'max:120'],
            'mensagem' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'titulo.required' => 'Informe o título do aviso.',
            'mensagem.required' => 'Escreva a mensagem.',
        ];
    }
}

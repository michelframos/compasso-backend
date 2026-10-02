<?php

namespace App\Modules\Academico\Http\Requests\SolicitacaoAula;

use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreSolicitacaoAulaRequest',
    required: ['tipo'],
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['criacao', 'cancelamento', 'reposicao', 'substituicao']),
        new OA\Property(property: 'id_aula_turma', type: 'integer', nullable: true, description: 'Obrigatório, exceto na criação'),
        new OA\Property(property: 'motivo', type: 'string', nullable: true, description: 'Obrigatório, exceto na criação'),
        new OA\Property(property: 'data_sugerida', type: 'string', format: 'date', nullable: true, description: 'Reposição e criação'),
        new OA\Property(property: 'hora_inicio_sugerida', type: 'string', nullable: true, example: '14:00'),
        new OA\Property(property: 'hora_termino_sugerida', type: 'string', nullable: true, example: '15:00'),
        new OA\Property(property: 'id_professor_substituto', type: 'integer', nullable: true, description: 'Substituição (opcional quando a escola exige aprovação: a secretaria escolhe)'),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Criação: aula de uma turma do professor'),
        new OA\Property(property: 'id_aluno_especifico', type: 'integer', nullable: true, description: 'Criação: aula individual (com id_curso)'),
        new OA\Property(property: 'id_curso', type: 'integer', nullable: true),
        new OA\Property(property: 'tipo_aula', type: 'string', nullable: true, enum: ['regular', 'reposicao', 'reforco', 'extra']),
        new OA\Property(property: 'destino_cobranca', type: 'string', nullable: true, enum: ['proxima_aula', 'cancelar'], description: 'Obrigatório no cancelamento ou reposição de aula com cobrança'),
        new OA\Property(property: 'avisar_alunos', type: 'boolean', default: true, description: 'Cancelamento, reposição e substituição: avisa alunos e responsáveis quando a alteração for aplicada'),
    ]
)]
class StoreSolicitacaoAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $criacao = $this->input('tipo') === SolicitacaoAula::TIPO_CRIACAO;
        $novoHorario = in_array($this->input('tipo'), [SolicitacaoAula::TIPO_CRIACAO, SolicitacaoAula::TIPO_REPOSICAO], true);

        return [
            'tipo' => ['required', Rule::in(SolicitacaoAula::TIPOS)],
            'id_aula_turma' => [Rule::requiredIf(! $criacao), Rule::prohibitedIf($criacao), 'nullable', 'integer', InstituicaoContext::existsRule('aulas_turmas')],
            'motivo' => [Rule::requiredIf(! $criacao), 'nullable', 'string', 'max:1000'],
            'data_sugerida' => [Rule::requiredIf($novoHorario), 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio_sugerida' => [Rule::requiredIf($novoHorario), 'nullable', 'date_format:H:i'],
            'hora_termino_sugerida' => [Rule::requiredIf($novoHorario), 'nullable', 'date_format:H:i', 'after:hora_inicio_sugerida'],
            'id_professor_substituto' => ['nullable', 'integer', InstituicaoContext::existsRule('professores')],
            'id_turma' => [Rule::requiredIf($criacao && ! $this->filled('id_aluno_especifico')), 'nullable', 'integer', InstituicaoContext::existsRule('turmas')],
            'id_aluno_especifico' => ['nullable', 'integer', InstituicaoContext::existsRule('alunos')],
            'id_curso' => [Rule::requiredIf($criacao && ! $this->filled('id_turma')), 'nullable', 'integer', InstituicaoContext::existsRule('cursos')],
            'tipo_aula' => ['nullable', Rule::in(SolicitacaoAula::TIPOS_AULA)],
            'destino_cobranca' => ['nullable', Rule::in(SolicitacaoAula::DESTINOS_COBRANCA)],
            'avisar_alunos' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_aula_turma.required' => 'Escolha a aula.',
            'motivo.required' => 'Informe o motivo.',
            'data_sugerida.required' => 'Informe a data da nova aula.',
            'data_sugerida.after_or_equal' => 'A nova aula não pode ser em data passada.',
            'hora_termino_sugerida.after' => 'O término deve ser depois do início.',
            'id_turma.required' => 'Escolha a turma ou o aluno da nova aula.',
            'id_curso.required' => 'Escolha o curso da aula individual.',
        ];
    }
}

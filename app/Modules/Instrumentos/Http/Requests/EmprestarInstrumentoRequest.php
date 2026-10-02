<?php

namespace App\Modules\Instrumentos\Http\Requests;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class EmprestarInstrumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['required', InstituicaoContext::existsRule('alunos')],
            'data_emprestimo' => 'required|date',
            'contrato_id' => ['nullable', InstituicaoContext::existsRule('contratos')],
            'contrato_gerado' => 'nullable|string',
            'gerar_conta' => 'nullable|boolean',
            'valor_conta' => 'required_if:gerar_conta,true|nullable|numeric|min:0',
            'data_vencimento_conta' => 'required_if:gerar_conta,true|nullable|date',
            'id_categoria_conta' => ['required_if:gerar_conta,true', 'nullable', InstituicaoContext::existsRule('categorias_contas')],
        ];
    }
}

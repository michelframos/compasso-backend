<?php

namespace App\Modules\Financeiro\Http\Requests\Conta;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateContaRequest",
    type: "object",
    properties: [
        new OA\Property(property: "id_categoria", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "descricao", type: "string", nullable: true, example: "Conta Atualizada"),
        new OA\Property(property: "valor", type: "number", format: "float", nullable: true, example: 100.50),
        new OA\Property(property: "data_vencimento", type: "string", format: "date", nullable: true, example: "2026-03-15"),
        new OA\Property(property: "data_pagamento", type: "string", format: "date", nullable: true, example: "2026-03-14"),
        new OA\Property(property: "status", type: "string", enum: ["pendente", "pago", "vencido", "cancelado"], nullable: true, example: "pago"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Pagamento efetuado"),
        new OA\Property(property: "id_aluno", type: "integer", nullable: true, example: null),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: null),
        new OA\Property(property: "id_matricula", type: "integer", nullable: true, example: 10),
        new OA\Property(property: "tipo", type: "string", enum: ["receita", "despesa"], nullable: true, example: "despesa"),
        new OA\Property(property: "numero_parcela", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "quantidade_parcelas", type: "integer", nullable: true, example: 12),
        new OA\Property(property: "mes_referencia", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "ano_referencia", type: "integer", nullable: true, example: 2026),
        new OA\Property(property: "notificar", type: "boolean", example: true)
    ]
)]
class UpdateContaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_categoria' => ['sometimes', InstituicaoContext::existsRule('categorias_contas')],
            'descricao' => ['sometimes', 'string', 'max:255'],
            'valor' => ['sometimes', 'numeric', 'min:0'],
            'data_vencimento' => ['sometimes', 'date'],
            'data_pagamento' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:pendente,pago,vencido,cancelado'],
            'observacoes' => ['nullable', 'string'],
            'id_aluno' => ['nullable', InstituicaoContext::existsRule('alunos')],
            'id_professor' => ['nullable', InstituicaoContext::existsRule('professores')],
            'id_matricula' => ['nullable', InstituicaoContext::existsRule('matriculas')],
            'tipo' => ['sometimes', 'in:receita,despesa'],
            'numero_parcela' => ['nullable', 'integer', 'min:1'],
            'quantidade_parcelas' => ['nullable', 'integer', 'min:1'],
            'mes_referencia' => ['nullable', 'integer', 'between:1,12'],
            'ano_referencia' => ['nullable', 'integer', 'min:2000'],
            'notificar' => ['nullable', 'boolean'],
        ];
    }
}

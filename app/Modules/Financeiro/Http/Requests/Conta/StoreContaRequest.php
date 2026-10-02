<?php

namespace App\Modules\Financeiro\Http\Requests\Conta;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreContaRequest",
    type: "object",
    required: ["id_categoria", "descricao", "valor", "data_vencimento", "tipo"],
    properties: [
        new OA\Property(property: "id_categoria", type: "integer", example: 1),
        new OA\Property(property: "descricao", type: "string", example: "Nova Conta a Pagar"),
        new OA\Property(property: "valor", type: "number", format: "float", example: 100.50),
        new OA\Property(property: "data_vencimento", type: "string", format: "date", example: "2026-03-15"),
        new OA\Property(property: "data_pagamento", type: "string", format: "date", nullable: true, example: null),
        new OA\Property(property: "status", type: "string", enum: ["pendente", "pago", "vencido", "cancelado"], example: "pendente"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Observações da conta"),
        new OA\Property(property: "id_aluno", type: "integer", nullable: true, example: null),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: null),
        new OA\Property(property: "id_matricula", type: "integer", nullable: true, example: 10),
        new OA\Property(property: "tipo", type: "string", enum: ["receita", "despesa"], example: "despesa"),
        new OA\Property(property: "numero_parcela", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "quantidade_parcelas", type: "integer", nullable: true, example: 12),
        new OA\Property(property: "mes_referencia", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "ano_referencia", type: "integer", nullable: true, example: 2026),
        new OA\Property(property: "notificar", type: "boolean", example: true)
    ]
)]
class StoreContaRequest extends FormRequest
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
            'id_categoria' => ['required', InstituicaoContext::existsRule('categorias_contas')],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0'],
            'data_vencimento' => ['required', 'date'],
            'data_pagamento' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pendente,pago,vencido,cancelado'],
            'observacoes' => ['nullable', 'string'],
            'id_aluno' => ['nullable', InstituicaoContext::existsRule('alunos')],
            'id_professor' => ['nullable', InstituicaoContext::existsRule('professores')],
            'id_matricula' => ['nullable', InstituicaoContext::existsRule('matriculas')],
            'tipo' => ['required', 'in:receita,despesa'],
            'numero_parcela' => ['nullable', 'integer', 'min:1'],
            'quantidade_parcelas' => ['nullable', 'integer', 'min:1'],
            'mes_referencia' => ['nullable', 'integer', 'between:1,12'],
            'ano_referencia' => ['nullable', 'integer', 'min:2000'],
            'repeticao' => ['nullable', 'in:diaria,semanal,mensal'],
            'quantidade_repeticoes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notificar' => ['nullable', 'boolean'],
        ];
    }
}

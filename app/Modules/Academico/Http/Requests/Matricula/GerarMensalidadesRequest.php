<?php

namespace App\Modules\Academico\Http\Requests\Matricula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "GerarMensalidadesRequest",
    title: "Gerar Mensalidades",
    description: "Parâmetros para geração de mensalidades em lote",
    required: ["valor", "quantidade_parcelas", "dia_vencimento", "data_inicio"],
    properties: [
        new OA\Property(property: "valor", type: "number", format: "float", example: 150.00),
        new OA\Property(property: "quantidade_parcelas", type: "integer", minimum: 1, maximum: 24, example: 6),
        new OA\Property(property: "dia_vencimento", type: "integer", minimum: 1, maximum: 31, example: 10),
        new OA\Property(property: "data_inicio", type: "string", format: "date", example: "2024-03-01"),
        new OA\Property(property: "id_categoria", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Geração automática via matrícula")
    ]
)]
class GerarMensalidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor' => 'required|numeric|min:0',
            'quantidade_parcelas' => 'required|integer|min:1|max:24',
            'dia_vencimento' => 'required|integer|min:1|max:31',
            'data_inicio' => 'required|date',
            'id_categoria' => ['nullable', InstituicaoContext::existsRule('categorias_contas')],
            'observacoes' => 'nullable|string'
        ];
    }
}

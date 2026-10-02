<?php

namespace App\Modules\Espetaculos\Http\Requests\Espetaculo;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreEspetaculoRequest",
    title: "Store Espetaculo Request",
    description: "Parâmetros para criação de um espetáculo",
    required: ["titulo", "data_evento"],
    properties: [
        new OA\Property(property: "titulo", type: "string", maxLength: 255, example: "Concerto de Inverno"),
        new OA\Property(property: "data_evento", type: "string", format: "date", example: "2026-07-15"),
        new OA\Property(property: "local", type: "string", maxLength: 255, example: "Teatro Municipal", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["planejamento", "ensaios", "concluido", "cancelado"], example: "planejamento", nullable: true),
        new OA\Property(property: "observacoes", type: "string", example: "Primeiro espetáculo da temporada.", nullable: true)
    ]
)]
class StoreEspetaculoRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'data_evento' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:planejamento,ensaios,concluido,cancelado'],
            'observacoes' => ['nullable', 'string'],
            'contrato_id' => ['nullable', InstituicaoContext::existsRule('contratos')],
        ];
    }
}

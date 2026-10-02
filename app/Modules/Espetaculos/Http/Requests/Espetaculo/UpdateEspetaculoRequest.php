<?php

namespace App\Modules\Espetaculos\Http\Requests\Espetaculo;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateEspetaculoRequest",
    title: "Update Espetaculo Request",
    description: "Parâmetros para atualização de um espetáculo",
    properties: [
        new OA\Property(property: "titulo", type: "string", maxLength: 255, example: "Concerto de Primavera", nullable: true),
        new OA\Property(property: "data_evento", type: "string", format: "date", example: "2026-09-22", nullable: true),
        new OA\Property(property: "local", type: "string", maxLength: 255, example: "Teatro Municipal", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["planejamento", "ensaios", "concluido", "cancelado"], example: "ensaios", nullable: true),
        new OA\Property(property: "observacoes", type: "string", example: "Nova data devido ao clima.", nullable: true)
    ]
)]
class UpdateEspetaculoRequest extends FormRequest
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
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'data_evento' => ['sometimes', 'required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:planejamento,ensaios,concluido,cancelado'],
            'observacoes' => ['nullable', 'string'],
            'contrato_id' => ['nullable', InstituicaoContext::existsRule('contratos')],
        ];
    }
}

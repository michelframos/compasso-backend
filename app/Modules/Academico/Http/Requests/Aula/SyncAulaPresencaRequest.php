<?php

namespace App\Modules\Academico\Http\Requests\Aula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Sync Aula Presenca Request",
    description: "Payload para sincronização em lote de presenças",
    type: "object",
    required: ["presencas"],
    properties: [
        new OA\Property(
            property: "presencas",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "id_aluno", type: "integer", example: 1),
                    new OA\Property(property: "status", type: "string", enum: ["presente", "falta", "falta_justificada"], example: "presente"),
                    new OA\Property(property: "observacao", type: "string", nullable: true, example: "Aluno apresentou atestado médico")
                ]
            )
        )
    ]
)]
class SyncAulaPresencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presencas' => 'required|array',
            'presencas.*.id_aluno' => ['required', InstituicaoContext::existsRule('alunos')],
            'presencas.*.status' => 'required|in:presente,falta,falta_justificada',
            'presencas.*.observacao' => 'nullable|string|max:1000',
            'conteudo_dado' => 'nullable|string|max:5000',
        ];
    }
}

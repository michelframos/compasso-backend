<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Histórico de Matrícula",
    description: "Modelo de Histórico de Matrícula",
    xml: new OA\Xml(name: "MatriculaHistorico"),
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_matricula", type: "integer", description: "ID da matrícula", example: 1),
        new OA\Property(property: "id_turma_origem", type: "integer", description: "ID da turma de origem", nullable: true, example: 1),
        new OA\Property(
            property: "turma_origem",
            type: "object",
            nullable: true,
            properties: [
                new OA\Property(property: "id", type: "integer", example: 1),
                new OA\Property(property: "nome", type: "string", example: "Violão - Nivel 1")
            ]
        ),
        new OA\Property(property: "id_turma_destino", type: "integer", description: "ID da turma de destino", example: 2),
        new OA\Property(
            property: "turma_destino",
            type: "object",
            properties: [
                new OA\Property(property: "id", type: "integer", example: 2),
                new OA\Property(property: "nome", type: "string", example: "Violão - Nivel 2")
            ]
        ),
        new OA\Property(property: "data_transferencia", type: "string", format: "date-time", description: "Data da transferência", example: "2023-11-05 14:00:00"),
        new OA\Property(property: "motivo", type: "string", description: "Motivo da transferência", nullable: true, example: "Mudança de horário"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", nullable: true, readOnly: true),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", nullable: true, readOnly: true)
    ]
)]
class MatriculaHistorico extends Model
{
    use HasFactory, PertenceAInstituicao;

    protected $table = 'matricula_historico';
    protected $fillable = ['id_instituicao', 'id_matricula', 'id_turma_origem', 'id_turma_destino', 'data_transferencia', 'motivo'];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function turmaOrigem()
    {
        return $this->belongsTo(Turma::class, 'id_turma_origem');
    }

    public function turmaDestino()
    {
        return $this->belongsTo(Turma::class, 'id_turma_destino');
    }
}

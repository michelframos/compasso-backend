<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Aula Presença",
    description: "Modelo de presença de um aluno em uma aula",
    xml: new OA\Xml(name: "AulaPresenca"),
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_aula_turma", type: "integer", description: "ID da aula vinculada", example: 1),
        new OA\Property(property: "id_aluno", type: "integer", description: "ID do aluno", example: 1),
        new OA\Property(property: "status", type: "string", description: "Status da presença (presente, falta, falta_justificada)", example: "presente"),
        new OA\Property(property: "observacao", type: "string", nullable: true, description: "Justificativa textual da falta", example: "Aluno apresentou atestado médico"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, readOnly: true)
    ]
)]
class AulaPresenca extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;
    protected $table = 'aulas_presencas';

    public const STATUS = ['presente', 'ausente', 'justificado'];

    protected $fillable = ['id_instituicao', 'id_aula_turma', 'id_aluno', 'status', 'observacao'];
    public $timestamps = false;

    public function aula() { return $this->belongsTo(AulaTurma::class, 'id_aula_turma'); }
    public function aluno() { return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno'); }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return $user->role === 'aluno' ? $query->where('id_aluno', $user->aluno?->id) : $query;
    }
}

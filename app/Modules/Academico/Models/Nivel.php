<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Nivel",
    description: "Modelo de Nível",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "nome", type: "string", example: "Iniciante 1"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Para alunos sem conhecimento prévio"),
        new OA\Property(property: "curso_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "ordem", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "idade_minima", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "idade_maxima", type: "integer", nullable: true, example: 6),
        new OA\Property(property: "cor_identificacao", type: "string", nullable: true, example: "#FFB6C1"),
        new OA\Property(property: "expectativas_aprendizado", type: "string", nullable: true, example: "Desenvolver coordenação motora básica")
    ]
)]
class Nivel extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'niveis';
    protected $fillable = [
        'id_instituicao',
        'nome',
        'observacoes', 
        'curso_id', 
        'ordem', 
        'idade_minima', 
        'idade_maxima', 
        'cor_identificacao', 
        'expectativas_aprendizado'
    ];
    public $timestamps = false;
    protected $dates = ['deleted_at'];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }
}

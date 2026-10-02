<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Curso",
    description: "Modelo de Curso",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "nome", type: "string", example: "Violão"),
        new OA\Property(property: "descricao", type: "string", nullable: true, example: "Curso de violão clássico")
    ]
)]
class Curso extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'cursos';
    protected $fillable = ['id_instituicao', 'nome', 'descricao', 'contrato_id'];
    public $timestamps = false;
    protected $dates = ['deleted_at'];

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }
}

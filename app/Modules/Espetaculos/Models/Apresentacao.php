<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Apresentacao",
    description: "Modelo de Apresentação",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_espetaculo", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "titulo_musica", type: "string", example: "O Trenzinho do Caipira"),
        new OA\Property(property: "ordem_entrada", type: "integer", example: 5),
        new OA\Property(property: "duracao_estimada", type: "string", example: "00:05:00")
    ]
)]
class Apresentacao extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'apresentacoes';
    protected $fillable = ['id_instituicao', 'id_espetaculo', 'id_turma', 'titulo_musica', 'ordem_entrada', 'duracao_estimada'];

    public function espetaculo()
    {
        return $this->belongsTo(Espetaculo::class, 'id_espetaculo');
    }
    public function turma()
    {
        return $this->belongsTo(\App\Models\Turma::class, 'id_turma');
    }
    public function alunos()
    {
        return $this->hasMany(ApresentacaoAluno::class, 'id_apresentacao');
    }
}

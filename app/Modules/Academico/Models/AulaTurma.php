<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Aula Turma",
    description: "Modelo de uma aula de uma turma",
    xml: new OA\Xml(name: "AulaTurma"),
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_turma", type: "integer", description: "ID da turma vinculada", example: 1),
        new OA\Property(property: "id_professor", type: "integer", description: "ID do professor da aula", example: 1),
        new OA\Property(property: "data", type: "string", format: "date", description: "Data da aula", example: "2023-11-05"),
        new OA\Property(property: "hora_inicio", type: "string", description: "Hora de início da aula", example: "14:00"),
        new OA\Property(property: "hora_termino", type: "string", description: "Hora de término da aula", example: "15:00"),
        new OA\Property(property: "status", type: "string", description: "Status da aula (agendada, concluida, cancelada)", nullable: true, example: "agendada"),
        new OA\Property(property: "tipo", type: "string", description: "Tipo da aula (regular, reposicao, reforco, extra)", example: "regular"),
        new OA\Property(property: "id_aluno_especifico", type: "integer", description: "ID do aluno para aulas de reposição individual", nullable: true, example: 1),
        new OA\Property(property: "conteudo_dado", type: "string", description: "Conteúdo ministrado na aula", nullable: true, example: "Introdução à percussão"),
        new OA\Property(property: "valor_hora_aula_aplicado", type: "number", format: "float", nullable: true, description: "Snapshot do valor hora/aula no dia da aula", example: 50.00),
        new OA\Property(property: "percentual_comissao_aplicado", type: "number", format: "float", nullable: true, description: "Snapshot do percentual de comissão no dia da aula", example: 30.0),
        new OA\Property(property: "valor_mensalidade_aplicado", type: "number", format: "float", nullable: true, description: "Snapshot do valor da mensalidade da turma no dia da aula", example: 350.00)
    ]
)]
class AulaTurma extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'aulas_turmas';
    protected $fillable = [
        'id_instituicao',
        'id_turma',
        'id_curso',
        'id_nivel',
        'id_professor',
        'data',
        'hora_inicio',
        'hora_termino',
        'status',
        'tipo',
        'id_aluno_especifico',
        'conteudo_dado',
        'valor_hora_aula_aplicado',
        'percentual_comissao_aplicado',
        'valor_mensalidade_aplicado',
        'notificar'
    ];

    protected $casts = [
        'notificar' => 'boolean',
        'data' => 'date',
    ];

    // public $timestamps = false; // Timestamps habilitados agora

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }
    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso');
    }
    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel');
    }
    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }
    public function aluno_especifico()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno_especifico');
    }
    public function presencas()
    {
        return $this->hasMany(AulaPresenca::class, 'id_aula_turma');
    }
    public function conta()
    {
        return $this->hasOne(\App\Models\Conta::class, 'id_aula_turma');
    }
}

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
    title: "Turma",
    description: "Modelo de Turma",
    xml: new OA\Xml(name: "Turma"),
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_curso", type: "integer", description: "ID do curso", example: 1),
        new OA\Property(property: "id_nivel", type: "integer", description: "ID do nivel", example: 1),
        new OA\Property(property: "id_professor", type: "integer", description: "ID do professor", example: 1),
        new OA\Property(property: "maximo_alunos", type: "integer", example: 15),
        new OA\Property(property: "descricao", type: "string", nullable: true),
        new OA\Property(property: "observacoes", type: "string", nullable: true),
        new OA\Property(property: "status", type: "string", example: "em_andamento"),
        new OA\Property(property: "tipo_agendamento", type: "string", example: "datas", description: "Tipo de agendamento: datas ou quantidade"),
        new OA\Property(property: "data_inicio", type: "string", format: "date", nullable: true, example: "2024-01-01"),
        new OA\Property(property: "data_fim", type: "string", format: "date", nullable: true, example: "2024-06-30"),
        new OA\Property(property: "quantidade_aulas", type: "integer", nullable: true, example: 20),
        new OA\Property(property: "valor_mensalidade", type: "number", format: "float", nullable: true, example: 150.00),
        new OA\Property(property: "percentual_comissao_especifico", type: "number", format: "float", nullable: true, example: 20.00),
        new OA\Property(property: "valor_hora_aula_especifico", type: "number", format: "float", nullable: true, example: 60.00)
    ]
)]
class Turma extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'turmas';
    protected $fillable = [
        'id_instituicao',
        'id_curso',
        'id_nivel',
        'id_professor',
        'maximo_alunos',
        'descricao',
        'observacoes',
        'status',
        'tipo_agendamento',
        'data_inicio',
        'data_fim',
        'quantidade_aulas',
        'valor_mensalidade',
        'percentual_comissao_especifico',
        'valor_hora_aula_especifico',
        'contrato_id'
    ];
    public $timestamps = false;

    protected $casts = [
        'status' => \App\Enums\TurmaStatus::class,
    ];

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
    public function horarios()
    {
        return $this->hasMany(TurmaHorario::class, 'id_turma');
    }
    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'id_turma');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->where('id_professor', $user->professor?->id ?? 0),
            'aluno' => $query->whereHas('matriculas', fn (Builder $q) => $q->where('id_aluno', $user->aluno?->id ?? 0)),
            'responsavel' => $query->whereHas('matriculas.aluno.responsaveis', fn (Builder $q) => $q
                ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id ?? 0)),
            default => $query,
        };
    }

    /** Nome curto mostrado a alunos e responsáveis: a descrição ou, sem ela, curso e nível. */
    public function apelido(): string
    {
        $base = collect([$this->curso?->nome, $this->nivel?->nome])->filter()->implode(' · ');

        return $this->descricao ?: ($base ?: 'Turma');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(\App\Modules\Academico\Observers\TurmaObserver::class);
    }
}

<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvaliacaoAluno extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const TIPOS = ['pratica', 'teorica', 'apresentacao', 'participacao', 'outra'];

    public const CONCEITOS = ['excelente', 'bom', 'regular', 'insuficiente'];

    /** Relações exibidas pelo AvaliacaoAlunoResource. */
    public const DETALHES = ['aluno.usuario', 'professor.usuario', 'turma.curso', 'turma.nivel'];

    protected $table = 'avaliacoes_alunos';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'id_turma',
        'id_professor',
        'data',
        'tipo',
        'nota',
        'conceito',
        'comentario',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'nota' => 'decimal:2',
        ];
    }

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    /** Professor vê as avaliações (inclusive de colegas) dos alunos com matrícula vigente com ele. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->whereIn(
                'id_aluno',
                Matricula::query()->visivelPara($user)->vigentes()->select('id_aluno')
            ),
            'aluno' => $query->where('id_aluno', $user->aluno?->id ?? 0),
            'responsavel' => $query->whereHas('aluno.responsaveis', fn (Builder $q) => $q
                ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id ?? 0)),
            default => $query,
        };
    }
}

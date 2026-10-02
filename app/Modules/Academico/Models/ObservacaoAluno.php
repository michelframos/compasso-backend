<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObservacaoAluno extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const TIPOS = ['comportamento', 'evolucao', 'alerta', 'geral'];

    protected $table = 'observacoes_alunos';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'id_professor',
        'id_turma',
        'tipo',
        'texto',
        'visivel_responsavel',
    ];

    protected function casts(): array
    {
        return ['visivel_responsavel' => 'boolean'];
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

    /** Relações exibidas pelo ObservacaoAlunoResource. */
    public const DETALHES = ['professor.usuario', 'turma.curso', 'turma.nivel'];

    /** Professor vê as observações (inclusive de colegas) dos alunos com matrícula vigente em suas turmas. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->whereIn(
                'id_aluno',
                Matricula::query()->visivelPara($user)->vigentes()->select('id_aluno')
            ),
            'aluno' => $query->whereRaw('1 = 0'),
            'responsavel' => $query
                ->where('visivel_responsavel', true)
                ->whereHas('aluno.responsaveis', fn (Builder $q) => $q
                    ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id ?? 0)),
            default => $query,
        };
    }
}

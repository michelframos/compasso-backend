<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Support\AlunosVinculados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitacaoReposicao extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_RECUSADA = 'recusada';

    public const STATUS = [self::STATUS_PENDENTE, self::STATUS_APROVADA, self::STATUS_RECUSADA];

    public const ABERTAS = [self::STATUS_PENDENTE, self::STATUS_APROVADA];

    protected $table = 'solicitacoes_reposicoes';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'id_aula_turma_perdida',
        'id_aula_turma_destino',
        'motivo',
        'status',
        'id_usuario_decisor',
        'decidido_em',
    ];

    protected function casts(): array
    {
        return [
            'decidido_em' => 'datetime',
        ];
    }

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function perdida()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_turma_perdida');
    }

    public function destino()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_turma_destino');
    }

    public function decisor()
    {
        return $this->belongsTo(User::class, 'id_usuario_decisor');
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'aluno', 'responsavel' => $query->whereIn('id_aluno', AlunosVinculados::ids($user)),
            'professor' => $query->where(function (Builder $pedido) use ($user): void {
                $professorId = $user->professor?->id ?? 0;
                $pedido->whereHas('perdida', fn (Builder $aula) => self::aulaDoProfessor($aula, $professorId))
                    ->orWhereHas('destino', fn (Builder $aula) => self::aulaDoProfessor($aula, $professorId));
            }),
            default => $query,
        };
    }

    public static function aulaDoProfessor(Builder $aula, int $professorId): void
    {
        $aula->where(fn (Builder $q) => $q
            ->where('id_professor', $professorId)
            ->orWhereHas('turma', fn (Builder $turma) => $turma->where('id_professor', $professorId)));
    }
}

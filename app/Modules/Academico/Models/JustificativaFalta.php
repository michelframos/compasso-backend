<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Support\AlunosVinculados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JustificativaFalta extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const TIPO_AVISO = 'aviso_previo';

    public const TIPO_JUSTIFICATIVA = 'justificativa';

    public const TIPOS = [self::TIPO_AVISO, self::TIPO_JUSTIFICATIVA];

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ACEITA = 'aceita';

    public const STATUS_RECUSADA = 'recusada';

    public const STATUS = [self::STATUS_PENDENTE, self::STATUS_ACEITA, self::STATUS_RECUSADA];

    protected $table = 'justificativas_faltas';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'id_aula_turma',
        'tipo',
        'motivo',
        'anexo',
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

    public function aula()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_turma');
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
            'professor' => $query->whereHas('aula', function (Builder $aula) use ($user): void {
                $professorId = $user->professor?->id ?? 0;
                $aula->where(fn (Builder $q) => $q
                    ->where('id_professor', $professorId)
                    ->orWhereHas('turma', fn (Builder $turma) => $turma->where('id_professor', $professorId)));
            }),
            default => $query,
        };
    }
}

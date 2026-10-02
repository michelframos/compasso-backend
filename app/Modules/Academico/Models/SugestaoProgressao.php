<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SugestaoProgressao extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    public const STATUS = [self::STATUS_PENDENTE, self::STATUS_APROVADA, self::STATUS_REJEITADA];

    /** Relações exibidas pelo SugestaoProgressaoResource. */
    public const DETALHES = [
        'matricula.aluno.usuario',
        'matricula.turma.curso',
        'matricula.turma.nivel',
        'matricula.curso',
        'matricula.nivel',
        'nivelAtual',
        'nivelSugerido',
        'professor.usuario',
        'turmaDestino.curso',
        'turmaDestino.nivel',
        'decisor',
    ];

    protected $table = 'sugestoes_progressao';

    protected $fillable = [
        'id_instituicao',
        'id_matricula',
        'id_nivel_atual',
        'id_nivel_sugerido',
        'id_professor',
        'status',
        'justificativa',
        'id_turma_destino',
        'id_usuario_decisor',
        'decidido_em',
        'motivo_decisao',
    ];

    protected function casts(): array
    {
        return ['decidido_em' => 'datetime'];
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function nivelAtual()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel_atual');
    }

    public function nivelSugerido()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel_sugerido');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    public function turmaDestino()
    {
        return $this->belongsTo(Turma::class, 'id_turma_destino');
    }

    public function decisor()
    {
        return $this->belongsTo(User::class, 'id_usuario_decisor');
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    /** Professor vê as sugestões (inclusive de colegas) das matrículas que estão com ele. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->whereIn('id_matricula', Matricula::query()->visivelPara($user)->select('id')),
            'admin', 'secretaria' => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }
}

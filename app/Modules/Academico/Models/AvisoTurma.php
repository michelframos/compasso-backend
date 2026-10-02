<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** Mensagem enviada a alunos e/ou responsáveis de uma turma ou de alunos escolhidos. */
class AvisoTurma extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const ORIGEM_MANUAL = 'manual';

    /** Gerado ao cancelar, repor ou trocar o professor de uma aula. */
    public const ORIGEM_AULA_ALTERADA = 'aula_alterada';

    public const ORIGENS = [self::ORIGEM_MANUAL, self::ORIGEM_AULA_ALTERADA];

    public const PUBLICO_ALUNOS = 'alunos';

    public const PUBLICO_RESPONSAVEIS = 'responsaveis';

    public const PUBLICO_AMBOS = 'ambos';

    public const PUBLICOS = [self::PUBLICO_ALUNOS, self::PUBLICO_RESPONSAVEIS, self::PUBLICO_AMBOS];

    /** Relações exibidas pelo AvisoTurmaResource. */
    public const DETALHES = ['turma.curso', 'turma.nivel', 'professor.usuario', 'autor'];

    protected $table = 'avisos_turmas';

    protected $fillable = [
        'id_instituicao',
        'id_turma',
        'id_professor',
        'id_usuario_autor',
        'id_aula_turma',
        'origem',
        'titulo',
        'mensagem',
        'publico',
        'canais',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'canais' => 'array',
            'enviado_em' => 'datetime',
        ];
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'id_usuario_autor');
    }

    public function aula()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_turma');
    }

    public function destinatarios()
    {
        return $this->hasMany(AvisoTurmaDestinatario::class, 'id_aviso_turma');
    }

    /** Professor vê os avisos das suas turmas e os que ele mesmo enviou; a equipe vê todos. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        if ($user->role === 'professor') {
            $idProfessor = $user->professor?->id ?? 0;

            return $query->where(fn (Builder $q) => $q
                ->where('id_professor', $idProfessor)
                ->orWhere('id_usuario_autor', $user->id)
                ->orWhereHas('turma', fn (Builder $t) => $t->where('id_professor', $idProfessor)));
        }

        return in_array($user->role, ['admin', 'secretaria'], true) ? $query : $query->whereRaw('1 = 0');
    }

    public function scopeComTotais(Builder $query): Builder
    {
        $porStatus = fn (string $status) => fn (Builder $q) => $q->where('status', $status);

        return $query->withCount([
            'destinatarios as total_destinatarios',
            'destinatarios as total_enviados' => $porStatus(AvisoTurmaDestinatario::STATUS_ENVIADO),
            'destinatarios as total_pendentes' => $porStatus(AvisoTurmaDestinatario::STATUS_PENDENTE),
            'destinatarios as total_erros' => $porStatus(AvisoTurmaDestinatario::STATUS_ERRO),
            'destinatarios as total_sem_contato' => $porStatus(AvisoTurmaDestinatario::STATUS_SEM_CONTATO),
            'destinatarios as total_alunos' => fn (Builder $q) => $q->select(DB::raw('count(distinct id_aluno)')),
        ]);
    }
}

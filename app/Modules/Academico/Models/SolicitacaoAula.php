<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pedido do professor para criar, cancelar, repor ou passar uma aula a um substituto. */
class SolicitacaoAula extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const TIPO_CRIACAO = 'criacao';

    public const TIPO_CANCELAMENTO = 'cancelamento';

    public const TIPO_REPOSICAO = 'reposicao';

    public const TIPO_SUBSTITUICAO = 'substituicao';

    public const TIPOS = [self::TIPO_CRIACAO, self::TIPO_CANCELAMENTO, self::TIPO_REPOSICAO, self::TIPO_SUBSTITUICAO];

    /** Permissão da escola que governa cada tipo de pedido. */
    public const ACAO_DO_TIPO = [
        self::TIPO_CRIACAO => PermissoesProfessorAulas::CRIAR,
        self::TIPO_CANCELAMENTO => PermissoesProfessorAulas::EXCLUIR,
        self::TIPO_REPOSICAO => PermissoesProfessorAulas::EDITAR,
        self::TIPO_SUBSTITUICAO => PermissoesProfessorAulas::EDITAR,
    ];

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS = [self::STATUS_PENDENTE, self::STATUS_APROVADA, self::STATUS_REJEITADA, self::STATUS_CANCELADA];

    /** A cobrança da aula cancelada vai para a próxima aula do aluno (na reposição, para a própria reposição). */
    public const COBRANCA_PROXIMA_AULA = 'proxima_aula';

    public const COBRANCA_CANCELAR = 'cancelar';

    public const DESTINOS_COBRANCA = [self::COBRANCA_PROXIMA_AULA, self::COBRANCA_CANCELAR];

    public const TIPOS_AULA = ['regular', 'reposicao', 'reforco', 'extra'];

    /** Relações exibidas pelo SolicitacaoAulaResource. */
    public const DETALHES = [
        'aula.turma.curso',
        'aula.turma.nivel',
        'aula.curso',
        'aula.aluno_especifico.usuario',
        'aula.professor.usuario',
        'professor.usuario',
        'substituto.usuario',
        'turma.curso',
        'turma.nivel',
        'curso',
        'alunoEspecifico.usuario',
        'aulaGerada.turma.curso',
        'aulaGerada.turma.nivel',
        'aulaGerada.curso',
        'aulaGerada.aluno_especifico.usuario',
        'aulaGerada.professor.usuario',
        'decisor',
    ];

    protected $table = 'solicitacoes_aulas';

    protected $attributes = [
        'avisar_alunos' => true,
    ];

    protected $fillable = [
        'id_instituicao',
        'id_aula_turma',
        'id_professor',
        'tipo',
        'motivo',
        'data_sugerida',
        'hora_inicio_sugerida',
        'hora_termino_sugerida',
        'id_professor_substituto',
        'id_turma',
        'id_curso',
        'id_aluno_especifico',
        'tipo_aula',
        'destino_cobranca',
        'avisar_alunos',
        'status',
        'id_usuario_decisor',
        'decidido_em',
        'motivo_decisao',
        'id_aula_gerada',
        'aplicada_automaticamente',
    ];

    protected function casts(): array
    {
        return [
            'data_sugerida' => 'date',
            'decidido_em' => 'datetime',
            'aplicada_automaticamente' => 'boolean',
            'avisar_alunos' => 'boolean',
        ];
    }

    public function aula()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_turma');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    public function substituto()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor_substituto');
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso');
    }

    public function alunoEspecifico()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno_especifico');
    }

    public function aulaGerada()
    {
        return $this->belongsTo(AulaTurma::class, 'id_aula_gerada');
    }

    public function decisor()
    {
        return $this->belongsTo(User::class, 'id_usuario_decisor');
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function acao(): string
    {
        return self::ACAO_DO_TIPO[$this->tipo];
    }
}

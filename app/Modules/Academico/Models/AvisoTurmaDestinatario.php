<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Uma pessoa num canal: a mesma pessoa avisada por e-mail e WhatsApp gera duas linhas. */
class AvisoTurmaDestinatario extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const TIPO_ALUNO = 'aluno';

    public const TIPO_RESPONSAVEL = 'responsavel';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ENVIADO = 'enviado';

    public const STATUS_ERRO = 'erro';

    /** Sem e-mail ou WhatsApp cadastrado para o canal: nada é enviado. */
    public const STATUS_SEM_CONTATO = 'sem_contato';

    protected $table = 'avisos_turmas_destinatarios';

    protected $fillable = [
        'id_instituicao',
        'id_aviso_turma',
        'id_aluno',
        'id_usuario',
        'tipo',
        'nome',
        'canal',
        'destino',
        'status',
        'erro',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em' => 'datetime',
        ];
    }

    public function aviso()
    {
        return $this->belongsTo(AvisoTurma::class, 'id_aviso_turma');
    }

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}

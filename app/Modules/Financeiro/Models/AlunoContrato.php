<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

class AlunoContrato extends Model
{
    use PertenceAInstituicao;

    protected $table = 'alunos_contratos';

    protected $fillable = [
        'id_instituicao',
        'aluno_id',
        'contrato_id',
        'contrato_gerado',
    ];

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'aluno_id');
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}

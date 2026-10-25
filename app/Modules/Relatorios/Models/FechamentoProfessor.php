<?php

namespace App\Modules\Relatorios\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Extrato mensal do professor congelado no fechamento (com a despesa gerada no Financeiro). */
class FechamentoProfessor extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    protected $table = 'fechamentos_professores';

    protected $fillable = [
        'id_instituicao',
        'id_professor',
        'ano',
        'mes',
        'salario_fixo',
        'total_horas',
        'valor_hora_aula',
        'valor_comissao',
        'valor_total',
        'detalhes',
        'id_conta',
        'id_usuario',
        'fechado_em',
    ];

    protected $casts = [
        'ano' => 'integer',
        'mes' => 'integer',
        'salario_fixo' => 'float',
        'total_horas' => 'float',
        'valor_hora_aula' => 'float',
        'valor_comissao' => 'float',
        'valor_total' => 'float',
        'detalhes' => 'array',
        'fechado_em' => 'datetime',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'id_professor');
    }

    public function conta()
    {
        return $this->belongsTo(Conta::class, 'id_conta')->withTrashed();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}

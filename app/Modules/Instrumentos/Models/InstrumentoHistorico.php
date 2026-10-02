<?php

namespace App\Modules\Instrumentos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;

class InstrumentoHistorico extends Model
{
    use PertenceAInstituicao;

    protected $fillable = [
        'id_instituicao',
        'id_instrumento',
        'id_aluno',
        'acao',
        'data',
        'observacoes',
        'contrato_id',
        'contrato_gerado',
    ];

    public function instrumento()
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento');
    }

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }
}

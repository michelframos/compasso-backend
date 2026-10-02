<?php
namespace App\Modules\Instrumentos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instrumento extends Model
{
    use HasFactory, PertenceAInstituicao;

    protected $fillable = [
        'id_instituicao',
        'nome',
        'tipo',
        'numero_serie',
        'status',
        'id_aluno',
        'data_emprestimo',
        'observacoes',
    ];

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function historicos()
    {
        return $this->hasMany(InstrumentoHistorico::class, 'id_instrumento');
    }
}

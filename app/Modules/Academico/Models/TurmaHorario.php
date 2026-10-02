<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TurmaHorario extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'turma_horarios';
    protected $fillable = ['id_instituicao', 'id_turma', 'dia_semana', 'hora_inicio', 'hora_termino'];

    public function turma() { return $this->belongsTo(Turma::class, 'id_turma'); }
}

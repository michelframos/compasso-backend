<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaterialTurma extends Model
{
    use PertenceAInstituicao;

    protected $table = 'materiais_turmas';
    protected $fillable = ['id_instituicao', 'id_turma', 'titulo', 'descricao', 'file_path', 'file_type', 'publico'];

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->whereHas('turma', fn (Builder $q) => $q->where('id_professor', $user->professor?->id)),
            'aluno' => $query->whereHas('turma.matriculas', fn (Builder $q) => $q->where('id_aluno', $user->aluno?->id)),
            default => $query,
        };
    }
}

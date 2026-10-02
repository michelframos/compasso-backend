<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApresentacaoAluno extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;
    protected $table = 'apresentacoes_alunos';
    protected $fillable = ['id_instituicao', 'id_apresentacao', 'id_aluno', 'tamanho_figurino', 'valor_figurino', 'pago_figurino', 'presenca_ensaio_geral', 'presenca_espetaculo', 'recebeu_figurino', 'fatura_gerada'];

    public function apresentacao()
    {
        return $this->belongsTo(Apresentacao::class, 'id_apresentacao');
    }
    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        if ($user->role !== 'professor') {
            return $query;
        }

        return $query->whereHas('apresentacao', fn (Builder $a) => $a->visivelPara($user));
    }
}

<?php

namespace App\Modules\Pessoas\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponsavelAluno extends Pivot
{
    use PertenceAInstituicao, SoftDeletes;

    protected $table = 'responsaveis_alunos';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'id_responsavel',
        'parentesco',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}

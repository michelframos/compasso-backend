<?php

namespace App\Modules\Pessoas\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Medida Aluno',
    description: 'Model de Medidas do Aluno',
    required: ['id_aluno'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'ID da medida'),
        new OA\Property(property: 'id_aluno', type: 'integer', format: 'int64', description: 'ID do aluno'),
        new OA\Property(property: 'medida_torax', type: 'number', format: 'float', description: 'Medida do tórax em cm'),
        new OA\Property(property: 'medida_cintura', type: 'number', format: 'float', description: 'Medida da cintura em cm'),
        new OA\Property(property: 'medida_quadril', type: 'number', format: 'float', description: 'Medida do quadril em cm'),
        new OA\Property(property: 'medida_altura', type: 'number', format: 'float', description: 'Altura em cm'),
        new OA\Property(property: 'ativo', type: 'boolean', description: 'Status da medida'),
        new OA\Property(property: 'criado_em', type: 'string', format: 'date-time', description: 'Data de criação'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
    ]
)]
class MedidaAluno extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'medidas_alunos';

    protected $fillable = [
        'id_instituicao',
        'id_aluno',
        'medida_torax',
        'medida_cintura',
        'medida_quadril',
        'medida_altura',
        'ativo',
        'criado_em',
    ];

    public $timestamps = false;

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'id_aluno');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'aluno' => $query->where('id_aluno', $user->aluno?->id),
            'responsavel' => $query->whereHas('aluno.responsaveis', fn (Builder $q) => $q
                ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id)),
            default => $query,
        };
    }
}

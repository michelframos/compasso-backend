<?php

namespace App\Modules\Pessoas\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Responsavel',
    description: 'Responsavel model',
    xml: new OA\Xml(name: 'Responsavel'),
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'id_usuario', type: 'integer', description: 'ID do usuário associado', example: 1),
        new OA\Property(property: 'observacoes', type: 'string', description: 'Observações do responsável', nullable: true, example: 'Pai do aluno João'),
        new OA\Property(property: 'criado_em', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'usuario', ref: '#/components/schemas/User'),
    ]
)]
class Responsavel extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'responsaveis';

    protected $fillable = [
        'id_instituicao',
        'id_usuario',
        'observacoes',
        'criado_em',
    ];

    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function alunos()
    {
        return $this->belongsToMany(Aluno::class, 'responsaveis_alunos', 'id_responsavel', 'id_aluno')
            ->using(ResponsavelAluno::class)
            ->withPivot('parentesco', 'observacoes', 'deleted_at')
            ->wherePivot('deleted_at', null);
    }
}

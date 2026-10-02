<?php

namespace App\Modules\Pessoas\Models;

use App\Models\AlunoContrato;
use App\Models\Apresentacao;
use App\Models\Conta;
use App\Models\Instrumento;
use App\Models\Matricula;
use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Aluno',
    description: 'Aluno model',
    xml: new OA\Xml(name: 'Aluno'),
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'id_usuario', type: 'integer', description: 'ID do usuário associado', example: 1),
        new OA\Property(property: 'observacoes', type: 'string', description: 'Observações do aluno', nullable: true, example: 'Aluno dedicado'),
        new OA\Property(property: 'criado_em', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'usuario', ref: '#/components/schemas/User'),
    ]
)]
class Aluno extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'alunos';

    protected $fillable = [
        'id_instituicao',
        'id_usuario',
        'id_lead',
        'observacoes',
        'criado_em',
    ];

    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function medidas()
    {
        return $this->hasMany(MedidaAluno::class, 'id_aluno');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(Responsavel::class, 'responsaveis_alunos', 'id_aluno', 'id_responsavel')
            ->using(ResponsavelAluno::class)
            ->withPivot('parentesco', 'observacoes', 'deleted_at')
            ->wherePivot('deleted_at', null);
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'id_aluno');
    }

    public function contas()
    {
        return $this->hasMany(Conta::class, 'id_aluno');
    }

    public function apresentacoes()
    {
        return $this->belongsToMany(Apresentacao::class, 'apresentacoes_alunos', 'id_aluno', 'id_apresentacao')
            ->withPivot('tamanho_figurino', 'pago_figurino', 'presenca_ensaio_geral')
            ->withTimestamps();
    }

    public function instrumentos()
    {
        return $this->hasMany(Instrumento::class, 'id_aluno');
    }

    public function contratosAvulsos()
    {
        return $this->hasMany(AlunoContrato::class, 'aluno_id');
    }
}

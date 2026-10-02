<?php

namespace App\Modules\Pessoas\Models;

use App\Models\Turma;
use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Professor',
    description: 'Professor model',
    xml: new OA\Xml(name: 'Professor'),
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'id_usuario', type: 'integer', description: 'ID do usuário associado', example: 1),
        new OA\Property(property: 'comissao', type: 'number', format: 'float', description: 'Percentual de comissão', example: 15.5),
        new OA\Property(property: 'salario_fixo', type: 'number', format: 'float', description: 'Salário fixo do professor', example: 3000.00),
        new OA\Property(property: 'valor_hora_aula', type: 'number', format: 'float', description: 'Valor por hora/aula ministrada', example: 50.00),
        new OA\Property(property: 'observacoes', type: 'string', description: 'Observações do professor', nullable: true, example: 'Especialista em violino'),
        new OA\Property(property: 'criado_em', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'usuario', ref: '#/components/schemas/User'),
    ]
)]
class Professor extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'professores';

    protected $fillable = [
        'id_instituicao',
        'id_usuario',
        'comissao',
        'salario_fixo',
        'valor_hora_aula',
        'observacoes',
        'criado_em',
    ];

    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class, 'id_professor');
    }
}

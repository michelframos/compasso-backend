<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Contrato",
    description: "Modelo de Contrato",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "nome", type: "string", example: "Contrato de Matrícula"),
        new OA\Property(property: "conteudo", type: "string", example: "Eu {{nome_aluno}} declaro que...")
    ]
)]
class Contrato extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'contratos';
    protected $fillable = ['id_instituicao', 'nome', 'conteudo'];
}

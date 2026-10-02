<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaConta extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;
    protected $table = 'categorias_contas';
    protected $fillable = ['id_instituicao', 'nome', 'tipo', 'descricao'];
    public $timestamps = false;
}

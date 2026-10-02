<?php

namespace App\Modules\Comercial\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    protected $fillable = ['id_instituicao', 'nome', 'email', 'telefone', 'status', 'observacoes'];
}

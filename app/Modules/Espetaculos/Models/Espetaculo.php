<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Espetaculo extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $fillable = ['id_instituicao', 'titulo', 'data_evento', 'local', 'status', 'observacoes', 'contrato_id'];

    public function apresentacoes()
    {
        return $this->hasMany(Apresentacao::class, 'id_espetaculo');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }
}

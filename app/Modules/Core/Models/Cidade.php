<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Cidade extends Model
{
    protected $table = 'cidades';

    protected $fillable = ['nome', 'id_estado', 'codigo_ibge', 'codigo_siafi', 'ddd'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }
}

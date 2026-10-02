<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estados';

    protected $fillable = ['nome', 'sigla', 'codigo_ibge'];

    public function cidades()
    {
        return $this->hasMany(Cidade::class, 'id_estado');
    }
}

<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoPlataforma extends Model
{
    protected $table = 'configuracoes_plataforma';

    protected $fillable = [
        'chave',
        'valor',
    ];
}

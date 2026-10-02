<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstituicaoUsuario extends Pivot
{
    use SoftDeletes;

    public const STATUS_ATIVO = 'a';

    public const STATUS_INATIVO = 'i';

    protected $table = 'instituicoes_usuarios';

    protected $fillable = [
        'id_instituicao',
        'id_usuario',
        'role',
        'status',
    ];

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'id_instituicao');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}

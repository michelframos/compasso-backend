<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformImpersonationLog extends Model
{
    protected $table = 'platform_impersonation_logs';

    protected $fillable = [
        'id_super_admin',
        'id_usuario_alvo',
        'id_instituicao',
        'token_id',
        'ip',
        'user_agent',
        'iniciado_em',
        'encerrado_em',
    ];

    protected function casts(): array
    {
        return [
            'iniciado_em' => 'datetime',
            'encerrado_em' => 'datetime',
        ];
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_super_admin');
    }

    public function usuarioAlvo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_alvo');
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'id_instituicao');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    public function isAtivo(): bool
    {
        return $this->encerrado_em === null;
    }
}

<?php

namespace App\Modules\Core\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'id_instituicao',
        'impersonator_user_id',
    ];

    public function impersonator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    public function isImpersonating(): bool
    {
        return $this->impersonator_user_id !== null;
    }
}

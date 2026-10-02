<?php

namespace App\Rules;

use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueEmailInInstituicao implements ValidationRule
{
    public function __construct(
        private readonly ?int $instituicaoId = null,
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filled($value)) {
            return;
        }

        $instituicaoId = $this->instituicaoId ?? InstituicaoContext::id();

        if ($instituicaoId === null) {
            return;
        }

        $exists = User::query()
            ->where('email', $value)
            ->when($this->ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreUserId))
            ->whereHas('instituicoes', function ($query) use ($instituicaoId): void {
                $query->where('instituicoes.id', $instituicaoId)
                    ->where('instituicoes_usuarios.status', InstituicaoUsuario::STATUS_ATIVO);
            })
            ->exists();

        if ($exists) {
            $fail('Este e-mail já está em uso nesta escola.');
        }
    }
}

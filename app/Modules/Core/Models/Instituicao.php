<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instituicao extends Model
{
    use SoftDeletes;

    public const STATUS_ATIVO = 'a';

    public const STATUS_INATIVO = 'i';

    public const STATUS_SUSPENSO = 's';

    public const ASSINATURA_TRIALING = 'trialing';

    public const ASSINATURA_ACTIVE = 'active';

    public const ASSINATURA_PAST_DUE = 'past_due';

    public const ASSINATURA_CANCELED = 'canceled';

    protected $table = 'instituicoes';

    protected $fillable = [
        'slug',
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'rua',
        'numero',
        'bairro',
        'complemento',
        'cep',
        'id_estado',
        'id_cidade',
        'status',
        'id_plano_assinatura',
        'trial_ends_at',
        'trial_usa_padrao',
        'assinatura_inicia_em',
        'assinatura_status',
    ];

    protected $hidden = [
        'codigo_ativacao',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'trial_usa_padrao' => 'boolean',
            'assinatura_inicia_em' => 'date',
            'codigo_ativacao_expira_em' => 'datetime',
            'ativada_em' => 'datetime',
        ];
    }

    public function aguardandoAtivacao(): bool
    {
        return $this->codigo_ativacao !== null && $this->ativada_em === null;
    }

    protected function cnpj(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Cnpj::normalize($value),
        );
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'id_cidade');
    }

    public function planoAssinatura(): BelongsTo
    {
        return $this->belongsTo(PlanoAssinatura::class, 'id_plano_assinatura');
    }

    public function impersonationLogs(): HasMany
    {
        return $this->hasMany(PlatformImpersonationLog::class, 'id_instituicao');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'instituicoes_usuarios', 'id_instituicao', 'id_usuario')
            ->using(InstituicaoUsuario::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }
}

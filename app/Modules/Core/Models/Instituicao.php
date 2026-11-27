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

    public const SUBCONTA_NONE = 'none';

    public const SUBCONTA_PENDING = 'pending';

    public const SUBCONTA_ACTIVE = 'active';

    public const SUBCONTA_FAILED = 'failed';

    public const MEIOS_COBRANCA_DISPONIVEIS = ['pix', 'credit_card', 'boleto'];

    public const MEIOS_COBRANCA_PADRAO = ['pix', 'boleto'];

    protected $table = 'instituicoes';

    protected $fillable = [
        'slug',
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'email_contato',
        'whatsapp',
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
        'modulos_desativados',
        'permissoes_professor_aulas',
        'limite_avisos_professor_dia',
        'permitir_aluno_editar_medidas',
        'limite_reposicoes_periodo',
        'prazo_reposicao_dias',
        'frequencia_minima',
        'modo_geracao_mensalidades',
        'dia_vencimento_mensalidade',
        'quantidade_parcelas_mensalidade',
        'inicio_parcelas_mensalidade',
        'gateway_subaccount_uuid',
        'gateway_subaccount_status',
        'gateway_subaccount_last_error',
        'meios_cobranca_online',
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
            'modulos_desativados' => 'array',
            'permissoes_professor_aulas' => 'array',
            'limite_avisos_professor_dia' => 'integer',
            'permitir_aluno_editar_medidas' => 'boolean',
            'limite_reposicoes_periodo' => 'integer',
            'prazo_reposicao_dias' => 'integer',
            'frequencia_minima' => 'decimal:2',
            'dia_vencimento_mensalidade' => 'integer',
            'quantidade_parcelas_mensalidade' => 'integer',
            'meios_cobranca_online' => 'array',
        ];
    }

    public function aguardandoAtivacao(): bool
    {
        return $this->codigo_ativacao !== null && $this->ativada_em === null;
    }

    /**
     * Meios de cobrança online habilitados pela escola (default: PIX e boleto).
     *
     * @return list<string>
     */
    public function meiosCobrancaOnline(): array
    {
        $meios = $this->meios_cobranca_online;

        if (! is_array($meios) || $meios === []) {
            return self::MEIOS_COBRANCA_PADRAO;
        }

        return array_values($meios);
    }

    public function subcontaAtiva(): bool
    {
        return $this->gateway_subaccount_status === self::SUBCONTA_ACTIVE;
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

<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\ConfiguracaoPlataforma;
use Illuminate\Support\Facades\Cache;

/**
 * Configurações globais da plataforma editáveis pelo super-admin (chave/valor).
 */
class PlatformSettings
{
    public const DEFAULT_TRIAL_DAYS = 'default_trial_days';

    public const EMAIL_CADASTRO_ASSUNTO = 'email_cadastro_assunto';

    public const EMAIL_CADASTRO_CORPO = 'email_cadastro_corpo';

    private const CACHE_KEY = 'platform_settings';

    public function get(string $chave, ?string $default = null): ?string
    {
        $valor = $this->all()[$chave] ?? null;

        return filled($valor) ? $valor : $default;
    }

    public function set(string $chave, ?string $valor): void
    {
        ConfiguracaoPlataforma::query()->updateOrCreate(
            ['chave' => $chave],
            ['valor' => $valor],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, string|null>  $valores
     */
    public function setMany(array $valores): void
    {
        foreach ($valores as $chave => $valor) {
            $this->set($chave, $valor);
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => ConfiguracaoPlataforma::query()
            ->pluck('valor', 'chave')
            ->all());
    }

    public function emailCadastroAssunto(): string
    {
        return $this->get(self::EMAIL_CADASTRO_ASSUNTO, self::defaultEmailCadastroAssunto());
    }

    public function emailCadastroCorpo(): string
    {
        return $this->get(self::EMAIL_CADASTRO_CORPO, self::defaultEmailCadastroCorpo());
    }

    public static function defaultEmailCadastroAssunto(): string
    {
        return 'Bem-vindo(a) ao Compasso, {{escola_nome}}! Confirme seu cadastro';
    }

    public static function defaultEmailCadastroCorpo(): string
    {
        return <<<'HTML'
<p>Olá, <strong>{{responsavel_nome}}</strong>!</p>
<p>O cadastro da escola <strong>{{escola_nome}}</strong> (CNPJ {{escola_cnpj}}) foi realizado com sucesso.</p>
<p>Você tem <strong>{{trial_dias}} dias</strong> de uso gratuito, até {{trial_termina_em}}.</p>
<p>No primeiro acesso, informe o código de ativação abaixo:</p>
<h2>{{codigo_ativacao}}</h2>
<p>Acesse: <a href="{{link_login}}">{{link_login}}</a></p>
<p>Equipe Compasso</p>
HTML;
    }
}

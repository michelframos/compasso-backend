<?php

namespace App\Modules\Core\Support;

/**
 * Substitui variáveis no formato {{nome}} pelos valores reais (escapados para HTML).
 */
class EmailTemplateRenderer
{
    /**
     * Variáveis disponíveis no e-mail de cadastro de escola.
     *
     * @var array<string, string>
     */
    public const VARIAVEIS_CADASTRO = [
        'escola_nome' => 'Nome fantasia da escola',
        'escola_cnpj' => 'CNPJ da escola',
        'responsavel_nome' => 'Nome do responsável',
        'responsavel_email' => 'E-mail do responsável',
        'codigo_ativacao' => 'Código de ativação (solicitado no primeiro acesso)',
        'trial_dias' => 'Dias de gratuidade',
        'trial_termina_em' => 'Data de término da gratuidade',
        'link_login' => 'Link da tela de login',
    ];

    /**
     * @param  array<string, string|int|null>  $valores
     */
    public function renderHtml(string $template, array $valores): string
    {
        return $this->replace($template, $valores, escape: true);
    }

    /**
     * @param  array<string, string|int|null>  $valores
     */
    public function renderText(string $template, array $valores): string
    {
        return $this->replace($template, $valores, escape: false);
    }

    public function contemVariavel(string $template, string $variavel): bool
    {
        return preg_match('/\{\{\s*'.preg_quote($variavel, '/').'\s*\}\}/', $template) === 1;
    }

    /**
     * @param  array<string, string|int|null>  $valores
     */
    private function replace(string $template, array $valores, bool $escape): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function (array $match) use ($valores, $escape): string {
                $chave = strtolower($match[1]);

                if (! array_key_exists($chave, $valores)) {
                    return $match[0];
                }

                $valor = (string) ($valores[$chave] ?? '');

                return $escape ? e($valor) : $valor;
            },
            $template,
        ) ?? $template;
    }
}

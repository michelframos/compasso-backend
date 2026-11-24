<?php

namespace App\Modules\Bilheteria\Support;

class LayoutIngresso
{
    public const VERSION = 1;

    /**
     * @return array{version: int, elementos: list<array<string, mixed>>}
     */
    public static function parse(?string $conteudo): array
    {
        if ($conteudo === null || trim($conteudo) === '') {
            return self::vazio();
        }

        $decoded = json_decode($conteudo, true);
        if (is_array($decoded) && (int) ($decoded['version'] ?? 0) === self::VERSION && isset($decoded['elementos']) && is_array($decoded['elementos'])) {
            return [
                'version' => self::VERSION,
                'elementos' => array_values($decoded['elementos']),
            ];
        }

        return self::vazio();
    }

    /**
     * @param  array{version?: int, elementos?: list<array<string, mixed>>}  $layout
     */
    public static function encode(array $layout): string
    {
        $elementos = array_map(static function (array $elemento): array {
            unset($elemento['url']);

            return $elemento;
        }, array_values($layout['elementos'] ?? []));

        return json_encode([
            'version' => self::VERSION,
            'elementos' => $elementos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array{version: int, elementos: list<array<string, mixed>>} */
    public static function vazio(): array
    {
        return [
            'version' => self::VERSION,
            'elementos' => [],
        ];
    }
}

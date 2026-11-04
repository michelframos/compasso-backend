<?php

namespace App\Modules\Academico\Support;

use Dompdf\Dompdf;

/**
 * Catálogo de fontes do editor de certificado (nativas DomPDF + Google Fonts embutidas).
 */
class CertificadoFontes
{
    private static bool $registradas = false;

    /**
     * @return array<string, array{css: string, familia: string, arquivos: array<string, string>}>
     */
    public static function googleEmbuidas(): array
    {
        return [
            'inter' => [
                'css' => 'Inter, sans-serif',
                'familia' => 'Inter',
                'arquivos' => [
                    '200' => 'Inter-ExtraLight.ttf',
                    '300' => 'Inter-Light.ttf',
                    'normal' => 'Inter-Regular.ttf',
                    'bold' => 'Inter-Bold.ttf',
                    '800' => 'Inter-ExtraBold.ttf',
                ],
            ],
            'roboto' => [
                'css' => 'Roboto, sans-serif',
                'familia' => 'Roboto',
                'arquivos' => [
                    '200' => 'Roboto-Thin.ttf',
                    '300' => 'Roboto-Light.ttf',
                    'normal' => 'Roboto-Regular.ttf',
                    'bold' => 'Roboto-Bold.ttf',
                    '800' => 'Roboto-Black.ttf',
                ],
            ],
            'montserrat' => [
                'css' => 'Montserrat, sans-serif',
                'familia' => 'Montserrat',
                'arquivos' => [
                    '200' => 'Montserrat-ExtraLight.ttf',
                    '300' => 'Montserrat-Light.ttf',
                    'normal' => 'Montserrat-Regular.ttf',
                    'bold' => 'Montserrat-Bold.ttf',
                    '800' => 'Montserrat-ExtraBold.ttf',
                ],
            ],
            'playfair' => [
                'css' => '"Playfair Display", serif',
                'familia' => 'Playfair Display',
                'arquivos' => [
                    'normal' => 'PlayfairDisplay-Regular.ttf',
                    'bold' => 'PlayfairDisplay-Bold.ttf',
                    '800' => 'PlayfairDisplay-Black.ttf',
                ],
            ],
            'lora' => [
                'css' => 'Lora, serif',
                'familia' => 'Lora',
                'arquivos' => [
                    'normal' => 'Lora-Regular.ttf',
                    '500' => 'Lora-Medium.ttf',
                    '600' => 'Lora-SemiBold.ttf',
                    'bold' => 'Lora-Bold.ttf',
                ],
            ],
            'great_vibes' => [
                'css' => '"Great Vibes", cursive',
                'familia' => 'Great Vibes',
                'arquivos' => [
                    'normal' => 'GreatVibes-Regular.ttf',
                ],
            ],
        ];
    }

    /** CSS font-family para o HTML do snapshot / preview. */
    public static function css(string $chave): string
    {
        $nativas = [
            'sans' => '"DejaVu Sans", Arial, sans-serif',
            'helvetica' => 'Helvetica, Arial, sans-serif',
            'serif' => '"DejaVu Serif", Georgia, serif',
            'times' => '"Times-Roman", "Times New Roman", Times, serif',
            'mono' => '"DejaVu Sans Mono", Consolas, monospace',
            'courier' => 'Courier, "Courier New", monospace',
        ];

        if (isset($nativas[$chave])) {
            return $nativas[$chave];
        }

        $google = self::googleEmbuidas();

        return $google[$chave]['css'] ?? $nativas['sans'];
    }

    /** CSS font-weight numérico a partir da chave do editor. */
    public static function pesoCss(mixed $peso): string
    {
        return match ((string) $peso) {
            'extralight', '200' => '200',
            'light', '300' => '300',
            'bold', '700' => '700',
            'extrabold', '800', '900' => '800',
            default => '400',
        };
    }

    public static function registrarNoDompdf(Dompdf $dompdf): void
    {
        if (self::$registradas) {
            return;
        }

        $dir = resource_path('fonts/certificado');
        $fontMetrics = $dompdf->getFontMetrics();

        foreach (self::googleEmbuidas() as $meta) {
            $familia = $meta['familia'];
            $fallback = $dir.DIRECTORY_SEPARATOR.($meta['arquivos']['normal'] ?? reset($meta['arquivos']));

            if (! is_file($fallback)) {
                continue;
            }

            foreach ($meta['arquivos'] as $peso => $arquivo) {
                $caminho = $dir.DIRECTORY_SEPARATOR.$arquivo;
                if (! is_file($caminho)) {
                    $caminho = $fallback;
                }

                $fontMetrics->registerFont(
                    ['family' => $familia, 'style' => 'normal', 'weight' => (string) $peso],
                    self::fileUri($caminho),
                );
            }

            // Garante pesos do editor mesmo quando a família não tem arquivo dedicado.
            foreach (['200', '300', 'normal', 'bold', '800'] as $pesoEditor) {
                if (isset($meta['arquivos'][$pesoEditor])) {
                    continue;
                }
                $fontMetrics->registerFont(
                    ['family' => $familia, 'style' => 'normal', 'weight' => $pesoEditor],
                    self::fileUri($fallback),
                );
            }
        }

        self::$registradas = true;
    }

    private static function fileUri(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            return $path;
        }

        // DomPDF validateLocalUri remove "file://" — em Windows precisa ficar "C:/...".
        return 'file://'.str_replace('\\', '/', $real);
    }
}

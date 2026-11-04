<?php

namespace App\Modules\Bilheteria\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class TemplateIngresso extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const DISCO_FUNDO = 'public';

    protected $table = 'templates_ingressos';

    protected $fillable = [
        'id_instituicao',
        'nome',
        'largura',
        'altura',
        'unidade',
        'conteudo',
        'fundo_path',
    ];

    protected $casts = [
        'largura' => 'float',
        'altura' => 'float',
    ];

    public function fundoUrl(): ?string
    {
        if (! $this->fundo_path) {
            return null;
        }

        return Storage::disk(self::DISCO_FUNDO)->url($this->fundo_path);
    }

    /** Dimensões em pixels para preview/PDF (96 DPI quando unidade = cm). */
    public function dimensoesEmPixels(): array
    {
        $largura = (float) $this->largura;
        $altura = (float) $this->altura;

        if ($this->unidade === 'cm') {
            $fator = 96 / 2.54;

            return [
                'largura' => (int) round($largura * $fator),
                'altura' => (int) round($altura * $fator),
            ];
        }

        return [
            'largura' => (int) round($largura),
            'altura' => (int) round($altura),
        ];
    }

    /** Dimensões em milímetros para DomPDF. */
    public function dimensoesEmMm(): array
    {
        $largura = (float) $this->largura;
        $altura = (float) $this->altura;

        if ($this->unidade === 'px') {
            $fator = 25.4 / 96;

            return [
                'largura' => round($largura * $fator, 2),
                'altura' => round($altura * $fator, 2),
            ];
        }

        return [
            'largura' => round($largura * 10, 2),
            'altura' => round($altura * 10, 2),
        ];
    }
}

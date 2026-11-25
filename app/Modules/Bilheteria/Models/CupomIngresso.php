<?php

namespace App\Modules\Bilheteria\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Database\Eloquent\Model;

class CupomIngresso extends Model
{
    use PertenceAInstituicao;

    protected $table = 'cupons_ingresso';

    protected $fillable = [
        'id_instituicao',
        'codigo',
        'tipo',
        'valor',
        'ativo',
        'valido_de',
        'valido_ate',
        'uso_maximo',
        'usos',
    ];

    protected $casts = [
        'valor' => 'float',
        'ativo' => 'boolean',
        'valido_de' => 'date',
        'valido_ate' => 'date',
        'uso_maximo' => 'integer',
        'usos' => 'integer',
    ];

    public function espetaculos()
    {
        return $this->belongsToMany(
            Espetaculo::class,
            'cupons_ingresso_espetaculos',
            'id_cupom_ingresso',
            'id_espetaculo'
        );
    }

    public function aplicarDesconto(float $valorBase): float
    {
        $desconto = $this->tipo === 'percentual'
            ? $valorBase * ((float) $this->valor / 100)
            : (float) $this->valor;

        return max(0, round($valorBase - $desconto, 2));
    }

    public function estaValidoPara(?int $idEspetaculo = null): bool
    {
        if (! $this->ativo) {
            return false;
        }

        $idsEspetaculos = $this->relationLoaded('espetaculos')
            ? $this->espetaculos->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->espetaculos()->pluck('espetaculos.id')->map(fn ($id) => (int) $id)->all();

        if (count($idsEspetaculos) > 0 && $idEspetaculo !== null && ! in_array($idEspetaculo, $idsEspetaculos, true)) {
            return false;
        }

        $hoje = now()->startOfDay();
        if ($this->valido_de && $hoje->lt($this->valido_de->startOfDay())) {
            return false;
        }
        if ($this->valido_ate && $hoje->gt($this->valido_ate->endOfDay())) {
            return false;
        }
        if ($this->uso_maximo !== null && $this->usos >= $this->uso_maximo) {
            return false;
        }

        return true;
    }
}

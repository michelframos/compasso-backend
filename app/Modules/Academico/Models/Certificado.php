<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Support\AlunosVinculados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificado extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    /** Frequência mínima (presenças / total) para emitir o certificado. */
    public const FREQUENCIA_MINIMA = 75.0;

    public const STATUS_MATRICULA = 'completada';

    protected $table = 'certificados';

    protected $fillable = [
        'id_instituicao',
        'id_matricula',
        'codigo_verificacao',
        'emitido_em',
        'carga_horaria',
    ];

    protected function casts(): array
    {
        return [
            'emitido_em' => 'datetime',
            'carga_horaria' => 'decimal:2',
        ];
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public static function gerarCodigo(): string
    {
        do {
            $codigo = 'CMP-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (static::withTrashed()->where('codigo_verificacao', $codigo)->exists());

        return $codigo;
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'aluno', 'responsavel' => $query->whereHas(
                'matricula',
                fn (Builder $matricula) => $matricula->whereIn('id_aluno', AlunosVinculados::ids($user))
            ),
            default => $query,
        };
    }
}

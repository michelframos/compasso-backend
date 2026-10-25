<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Pessoas\Models\Professor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Janela semanal em que o professor pode dar aulas (usada como aviso no agendamento). */
class DisponibilidadeProfessor extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    /** Na ordem da semana; o índice segue o `dayOfWeek` do Carbon (domingo = 0). */
    public const DIAS_SEMANA = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];

    protected $table = 'disponibilidades_professores';

    protected $fillable = ['id_instituicao', 'id_professor', 'dia_semana', 'hora_inicio', 'hora_termino'];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'id_professor');
    }

    public static function diaDa(CarbonInterface $data): string
    {
        return self::DIAS_SEMANA[$data->dayOfWeek];
    }

    /** Janelas do professor de segunda a domingo. */
    public static function doProfessor(int $idProfessor): Collection
    {
        $ordem = array_flip(['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo']);

        return static::query()
            ->where('id_professor', $idProfessor)
            ->orderBy('hora_inicio')
            ->get()
            ->sortBy(fn (self $janela) => $ordem[$janela->dia_semana] ?? 7)
            ->values();
    }
}

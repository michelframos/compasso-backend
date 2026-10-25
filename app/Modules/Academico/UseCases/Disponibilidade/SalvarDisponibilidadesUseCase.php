<?php

namespace App\Modules\Academico\UseCases\Disponibilidade;

use App\Modules\Academico\Models\DisponibilidadeProfessor;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalvarDisponibilidadesUseCase
{
    /**
     * @param  array<int, array{dia_semana: string, hora_inicio: string, hora_termino: string}>  $janelas
     * @return Collection<int, DisponibilidadeProfessor>
     */
    public function execute(Professor $professor, array $janelas): Collection
    {
        return DB::transaction(function () use ($professor, $janelas): Collection {
            DisponibilidadeProfessor::query()->where('id_professor', $professor->id)->delete();

            foreach ($janelas as $janela) {
                DisponibilidadeProfessor::create([
                    'id_professor' => $professor->id,
                    'dia_semana' => $janela['dia_semana'],
                    'hora_inicio' => $janela['hora_inicio'],
                    'hora_termino' => $janela['hora_termino'],
                ]);
            }

            return DisponibilidadeProfessor::doProfessor($professor->id);
        });
    }
}

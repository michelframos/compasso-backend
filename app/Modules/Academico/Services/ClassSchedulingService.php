<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Models\AulaTurma;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ClassSchedulingService
{
    public function generateClasses(Turma $turma)
    {
        if ($turma->tipo_agendamento === 'datas') {
            return $this->generateByDates($turma);
        }

        return $this->generateByQuantity($turma);
    }

    private function generateByDates(Turma $turma)
    {
        if (!$turma->data_inicio || !$turma->data_fim) {
            return;
        }

        $horarios = $turma->horarios;
        if ($horarios->isEmpty()) {
            return;
        }

        $period = CarbonPeriod::create($turma->data_inicio, $turma->data_fim);
        $diasSemanaMap = [
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'domingo' => 0,
        ];

        foreach ($period as $date) {
            foreach ($horarios as $horario) {
                if ($date->dayOfWeek === $diasSemanaMap[$horario->dia_semana]) {
                    $this->createClass($turma, $date, $horario);
                }
            }
        }
    }

    private function generateByQuantity(Turma $turma)
    {
        if (!$turma->data_inicio || !$turma->quantidade_aulas) {
            return;
        }

        $horarios = $turma->horarios->sortBy(function ($h) {
            $diasSemanaMap = [
                'segunda' => 1,
                'terca' => 2,
                'quarta' => 3,
                'quinta' => 4,
                'sexta' => 5,
                'sabado' => 6,
                'domingo' => 0,
            ];
            return $diasSemanaMap[$h->dia_semana];
        });

        if ($horarios->isEmpty()) {
            return;
        }

        $aulasCriadas = 0;
        $currentDate = Carbon::parse($turma->data_inicio);
        $maxIterations = 1000; // Segurança contra loops infinitos
        $iterations = 0;

        $diasSemanaMap = [
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'domingo' => 0,
        ];

        while ($aulasCriadas < $turma->quantidade_aulas && $iterations < $maxIterations) {
            foreach ($horarios as $horario) {
                if ($currentDate->dayOfWeek === $diasSemanaMap[$horario->dia_semana]) {
                    if ($aulasCriadas < $turma->quantidade_aulas) {
                        $this->createClass($turma, $currentDate, $horario);
                        $aulasCriadas++;
                        $ultimoDia = $currentDate->toDateString();
                    }
                }
            }
            $currentDate->addDay();
            $iterations++;
        }

        if (isset($ultimoDia) && $turma->data_fim !== $ultimoDia) {
            $turma->update(['data_fim' => $ultimoDia]);
        }
    }

    private function createClass(Turma $turma, Carbon $date, $horario)
    {
        // Verifica se já existe aula nesse dia/horário para evitar duplicatas em re-processamentos
        $exists = AulaTurma::where('id_turma', $turma->id)
            ->where('data', $date->toDateString())
            ->where('hora_inicio', $horario->hora_inicio)
            ->exists();

        if (!$exists) {
            AulaTurma::create([
                'id_turma' => $turma->id,
                'id_professor' => $turma->id_professor,
                'data' => $date->toDateString(),
                'hora_inicio' => $horario->hora_inicio,
                'hora_termino' => $horario->hora_termino,
                'status' => 'agendada',
                'tipo' => 'regular',
            ]);
        }
    }
}

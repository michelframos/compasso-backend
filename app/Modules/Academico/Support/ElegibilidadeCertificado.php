<?php

namespace App\Modules\Academico\Support;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Queries\FrequenciaAlunoQuery;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ElegibilidadeCertificado
{
    public function __construct(private readonly FrequenciaAlunoQuery $frequencia) {}

    /**
     * @return array{percentual: float|null, carga_horaria: float}
     */
    public function avaliar(Matricula $matricula): array
    {
        if ($matricula->status !== Certificado::STATUS_MATRICULA) {
            throw ValidationException::withMessages([
                'id_matricula' => 'Só é possível emitir certificado de matrícula completada.',
            ]);
        }

        $resumo = $this->frequencia->montar((int) $matricula->id_aluno, $matricula->id)['resumo'];
        $percentual = $resumo['percentual'];

        if ($resumo['total'] === 0) {
            throw ValidationException::withMessages([
                'id_matricula' => 'Não há chamadas registradas para calcular a frequência.',
            ]);
        }

        if ($percentual === null || $percentual < Certificado::FREQUENCIA_MINIMA) {
            throw ValidationException::withMessages([
                'id_matricula' => 'A frequência mínima para o certificado é '.Certificado::FREQUENCIA_MINIMA.'%.',
            ]);
        }

        return [
            'percentual' => $percentual,
            'carga_horaria' => $this->cargaHoraria($matricula),
        ];
    }

    public function cargaHoraria(Matricula $matricula): float
    {
        $matricula->loadMissing('historicos');
        $idsTurma = $this->idsTurma($matricula);

        $presencas = AulaPresenca::query()
            ->where('id_aluno', $matricula->id_aluno)
            ->whereIn('status', ['presente', 'justificado'])
            ->whereHas('aula', function ($aula) use ($matricula, $idsTurma): void {
                $aula->where(fn ($status) => $status->whereNull('status')->orWhere('status', '!=', 'cancelada'))
                    ->where(function ($escopo) use ($matricula, $idsTurma): void {
                        if ($idsTurma !== []) {
                            $escopo->whereIn('id_turma', $idsTurma);
                        }
                        if ($matricula->tipo === 'curso') {
                            $escopo->orWhere(fn ($individual) => $individual
                                ->whereNull('id_turma')
                                ->where('id_aluno_especifico', $matricula->id_aluno)
                                ->when($matricula->id_curso, fn ($q) => $q->where('id_curso', $matricula->id_curso)));
                        }
                    });
            })
            ->with('aula')
            ->get();

        $minutos = $presencas->sum(function (AulaPresenca $presenca) {
            $inicio = Carbon::parse((string) $presenca->aula->hora_inicio);
            $termino = Carbon::parse((string) $presenca->aula->hora_termino);

            return max(0, $inicio->diffInMinutes($termino));
        });

        return round($minutos / 60, 2);
    }

    /** @return list<int> */
    private function idsTurma(Matricula $matricula): array
    {
        $ids = collect();

        if ($matricula->id_turma) {
            $ids->push((int) $matricula->id_turma);
        }

        foreach ($matricula->historicos as $historico) {
            if ($historico->id_turma_origem) {
                $ids->push((int) $historico->id_turma_origem);
            }
            if ($historico->id_turma_destino) {
                $ids->push((int) $historico->id_turma_destino);
            }
        }

        return $ids->unique()->values()->all();
    }
}

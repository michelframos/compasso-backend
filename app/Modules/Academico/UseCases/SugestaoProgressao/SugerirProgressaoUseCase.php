<?php

namespace App\Modules\Academico\UseCases\SugestaoProgressao;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SugerirProgressaoUseCase
{
    public function execute(Matricula $matricula, Nivel $nivelSugerido, string $justificativa, Professor $autor): SugestaoProgressao
    {
        return DB::transaction(function () use ($matricula, $nivelSugerido, $justificativa, $autor): SugestaoProgressao {
            $matricula = Matricula::query()->with('turma')->lockForUpdate()->findOrFail($matricula->id);
            $idNivelAtual = $matricula->idNivelAtual();

            $erro = match (true) {
                ! $matricula->estaVigente() => ['id_matricula', 'A matrícula não está vigente.'],
                $nivelSugerido->id === (int) $idNivelAtual => ['id_nivel_sugerido', 'O aluno já está neste nível.'],
                $nivelSugerido->curso_id !== null && (int) $nivelSugerido->curso_id !== (int) $matricula->idCursoAtual()
                    => ['id_nivel_sugerido', 'O nível sugerido pertence a outro curso.'],
                SugestaoProgressao::query()
                    ->where('id_matricula', $matricula->id)
                    ->where('status', SugestaoProgressao::STATUS_PENDENTE)
                    ->exists() => ['id_matricula', 'Já existe uma sugestão pendente para esta matrícula.'],
                default => null,
            };

            if ($erro !== null) {
                throw ValidationException::withMessages([$erro[0] => $erro[1]]);
            }

            return SugestaoProgressao::create([
                'id_matricula' => $matricula->id,
                'id_nivel_atual' => $idNivelAtual,
                'id_nivel_sugerido' => $nivelSugerido->id,
                'id_professor' => $autor->id,
                'status' => SugestaoProgressao::STATUS_PENDENTE,
                'justificativa' => $justificativa,
            ]);
        });
    }
}

<?php

namespace App\Modules\Academico\UseCases\JustificativaFalta;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidirJustificativaUseCase
{
    /** @param  array{decisao: string}  $dados */
    public function execute(JustificativaFalta $justificativa, array $dados, User $decisor): JustificativaFalta
    {
        return DB::transaction(function () use ($justificativa, $dados, $decisor) {
            $justificativa = JustificativaFalta::query()->lockForUpdate()->findOrFail($justificativa->id);

            if (! $justificativa->estaPendente()) {
                throw ValidationException::withMessages(['status' => 'Esta justificativa já foi decidida.']);
            }

            if ($dados['decisao'] === JustificativaFalta::STATUS_ACEITA) {
                AulaPresenca::updateOrCreate(
                    ['id_aula_turma' => $justificativa->id_aula_turma, 'id_aluno' => $justificativa->id_aluno],
                    ['status' => 'justificado', 'observacao' => $justificativa->motivo]
                );
            }

            $justificativa->update([
                'status' => $dados['decisao'],
                'id_usuario_decisor' => $decisor->id,
                'decidido_em' => now(),
            ]);

            return $justificativa->fresh(['aluno.usuario', 'aula.turma']);
        });
    }
}

<?php

namespace App\Modules\Academico\UseCases\Matricula;

use App\Modules\Academico\Models\Matricula;
use Illuminate\Support\Facades\DB;

class CreateMatriculaUseCase
{
    public function execute(array $dados): Matricula
    {
        return DB::transaction(function () use ($dados) {
            if (($dados['tipo'] ?? null) === 'turma' && ! empty($dados['id_turma'])) {
                $excluida = Matricula::onlyTrashed()
                    ->where('id_aluno', $dados['id_aluno'])
                    ->where('id_turma', $dados['id_turma'])
                    ->latest('deleted_at')
                    ->first();

                if ($excluida) {
                    $excluida->restore();
                    $excluida->update($dados);

                    return $excluida->fresh([
                        'aluno.usuario',
                        'turma.curso',
                        'turma.nivel',
                        'curso',
                        'nivel',
                        'professor.usuario',
                    ]);
                }
            }

            return Matricula::create($dados)->load([
                'aluno.usuario',
                'turma.curso',
                'turma.nivel',
                'curso',
                'nivel',
                'professor.usuario',
            ]);
        });
    }
}

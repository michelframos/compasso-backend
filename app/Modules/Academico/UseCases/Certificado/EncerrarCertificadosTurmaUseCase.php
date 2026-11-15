<?php

namespace App\Modules\Academico\UseCases\Certificado;

use App\Enums\TurmaStatus;
use App\Modules\Academico\Http\Resources\CertificadoResource;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\ElegibilidadeCertificado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class EncerrarCertificadosTurmaUseCase
{
    public function __construct(
        private readonly ElegibilidadeCertificado $elegibilidade,
        private readonly EmitirCertificadoUseCase $emitir,
    ) {}

    /**
     * @param  list<int>  $matriculaIds
     * @return array{emitidos: list<CertificadoResource>, falhas: list<array{id_matricula: int, mensagem: string}>}
     */
    public function execute(Turma $turma, array $matriculaIds): array
    {
        if ($turma->status !== TurmaStatus::CONCLUIDA) {
            throw ValidationException::withMessages([
                'turma' => 'Só é possível emitir certificados de encerramento em turma concluída.',
            ]);
        }

        $emitidos = [];
        $falhas = [];

        $matriculas = Matricula::query()
            ->whereIn('id', $matriculaIds)
            ->with(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'historicos'])
            ->get()
            ->keyBy('id');

        foreach ($matriculaIds as $idMatricula) {
            $matricula = $matriculas->get($idMatricula);

            if (! $matricula) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'Matrícula não encontrada.',
                ];

                continue;
            }

            if ((int) $matricula->id_turma !== (int) $turma->id) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'A matrícula não pertence a esta turma.',
                ];

                continue;
            }

            if (! in_array($matricula->status, ['ativa', 'completada'], true)) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'Só é possível emitir para matrículas ativas ou completadas.',
                ];

                continue;
            }

            try {
                Gate::authorize('emitir', [Certificado::class, $matricula]);
                $this->elegibilidade->avaliarFrequencia($matricula);

                if ($matricula->status !== Certificado::STATUS_MATRICULA) {
                    $matricula->update(['status' => Certificado::STATUS_MATRICULA]);
                    $matricula->refresh();
                }

                $emitidos[] = new CertificadoResource($this->emitir->execute($matricula));
            } catch (ValidationException $e) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => collect($e->errors())->flatten()->first() ?? 'Não foi possível emitir.',
                ];
            } catch (Throwable $e) {
                $falhas[] = [
                    'id_matricula' => $idMatricula,
                    'mensagem' => 'Não foi possível emitir o certificado.',
                ];
            }
        }

        return [
            'emitidos' => $emitidos,
            'falhas' => $falhas,
        ];
    }
}

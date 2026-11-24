<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\ElegibilidadeCertificado;

class ListarEncerramentoCertificadosTurma
{
    private const STATUS_CANDIDATOS = ['ativa', 'completada'];

    public function __construct(private readonly ElegibilidadeCertificado $elegibilidade) {}

    /**
     * @return array{
     *   aptos: list<array<string, mixed>>,
     *   inaptos: list<array<string, mixed>>
     * }
     */
    public function execute(Turma $turma): array
    {
        $emitidas = Certificado::query()->pluck('id_matricula')->all();

        $matriculas = Matricula::query()
            ->where('id_turma', $turma->id)
            ->whereIn('status', self::STATUS_CANDIDATOS)
            ->when($emitidas !== [], fn ($q) => $q->whereNotIn('id', $emitidas))
            ->with(['aluno.usuario', 'turma', 'historicos'])
            ->orderBy('id')
            ->get();

        $aptos = [];
        $inaptos = [];

        foreach ($matriculas as $matricula) {
            $base = [
                'id' => $matricula->id,
                'aluno' => $matricula->aluno ? [
                    'id' => $matricula->aluno->id,
                    'nome' => $matricula->aluno->usuario?->nome,
                ] : null,
                'status' => $matricula->status,
            ];

            $preview = $this->elegibilidade->previewFrequencia($matricula);

            if ($preview !== null) {
                $aptos[] = [
                    ...$base,
                    'frequencia_percentual' => $preview['percentual'],
                    'frequencia_minima' => $preview['frequencia_minima'],
                    'carga_horaria' => $preview['carga_horaria'],
                ];

                continue;
            }

            $diagnostico = $this->elegibilidade->diagnosticoFrequencia($matricula);
            $inaptos[] = [
                ...$base,
                'frequencia_percentual' => $diagnostico['percentual'],
                'frequencia_minima' => $diagnostico['frequencia_minima'],
                'carga_horaria' => $diagnostico['carga_horaria'],
                'motivo' => $diagnostico['motivo'],
            ];
        }

        return [
            'aptos' => $aptos,
            'inaptos' => $inaptos,
        ];
    }
}

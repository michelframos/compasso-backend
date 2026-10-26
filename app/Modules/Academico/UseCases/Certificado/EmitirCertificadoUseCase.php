<?php

namespace App\Modules\Academico\UseCases\Certificado;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Support\ElegibilidadeCertificado;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmitirCertificadoUseCase
{
    public function __construct(private readonly ElegibilidadeCertificado $elegibilidade) {}

    public function execute(Matricula $matricula): Certificado
    {
        $jaEmitido = Certificado::query()->where('id_matricula', $matricula->id)->exists();

        if ($jaEmitido) {
            throw ValidationException::withMessages([
                'id_matricula' => 'Esta matrícula já tem um certificado emitido.',
            ]);
        }

        $dados = $this->elegibilidade->avaliar($matricula);

        return DB::transaction(fn () => Certificado::create([
            'id_matricula' => $matricula->id,
            'codigo_verificacao' => Certificado::gerarCodigo(),
            'emitido_em' => now(),
            'carga_horaria' => $dados['carga_horaria'],
        ])->fresh(['matricula.aluno.usuario', 'matricula.turma.curso', 'matricula.turma.nivel', 'matricula.curso', 'matricula.nivel']));
    }
}

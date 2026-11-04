<?php

namespace App\Modules\Academico\UseCases\Certificado;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Services\SubstituirPlaceholdersCertificado;
use App\Modules\Academico\Services\TemplateCertificadoFundoService;
use App\Modules\Academico\Support\ElegibilidadeCertificado;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmitirCertificadoUseCase
{
    public function __construct(
        private readonly ElegibilidadeCertificado $elegibilidade,
        private readonly SubstituirPlaceholdersCertificado $placeholders,
        private readonly TemplateCertificadoFundoService $fundos,
    ) {}

    public function execute(Matricula $matricula): Certificado
    {
        $jaEmitido = Certificado::query()->where('id_matricula', $matricula->id)->exists();

        if ($jaEmitido) {
            throw ValidationException::withMessages([
                'id_matricula' => 'Esta matrícula já tem um certificado emitido.',
            ]);
        }

        $dados = $this->elegibilidade->avaliar($matricula);
        $template = $this->resolverTemplate($matricula);

        return DB::transaction(function () use ($matricula, $dados, $template) {
            $certificado = Certificado::create([
                'id_matricula' => $matricula->id,
                'codigo_verificacao' => Certificado::gerarCodigo(),
                'emitido_em' => now(),
                'carga_horaria' => $dados['carga_horaria'],
            ]);

            if ($template) {
                $certificado->loadMissing('instituicao');
                $conteudo = $this->placeholders->montar($template->conteudo, $certificado, $matricula);
                $fundo = $this->fundos->copiarParaSnapshot($template->fundo_path, $certificado->id);

                $certificado->update([
                    'conteudo_gerado' => $conteudo,
                    'fundo_path' => $fundo,
                ]);
            }

            return $certificado->fresh([
                'matricula.aluno.usuario',
                'matricula.turma.curso',
                'matricula.turma.nivel',
                'matricula.curso',
                'matricula.nivel',
            ]);
        });
    }

    private function resolverTemplate(Matricula $matricula): ?TemplateCertificado
    {
        $matricula->loadMissing(['turma.curso.templateCertificado', 'curso.templateCertificado']);

        $curso = $matricula->tipo === 'curso'
            ? $matricula->curso
            : $matricula->turma?->curso;

        return $curso?->templateCertificado;
    }
}

<?php

namespace App\Modules\Academico\UseCases\Certificado;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Services\SubstituirPlaceholdersCertificado;
use App\Modules\Academico\Services\TemplateCertificadoFundoService;
use App\Modules\Academico\Support\ElegibilidadeCertificado;
use App\Modules\Academico\Support\ResolverTemplateCertificado;
use Illuminate\Support\Facades\DB;

class ReemitirCertificadoUseCase
{
    public function __construct(
        private readonly ElegibilidadeCertificado $elegibilidade,
        private readonly SubstituirPlaceholdersCertificado $placeholders,
        private readonly TemplateCertificadoFundoService $fundos,
        private readonly ResolverTemplateCertificado $resolverTemplate,
    ) {}

    public function execute(Certificado $certificado, ?int $idTemplate = null): Certificado
    {
        $certificado->loadMissing([
            'matricula.aluno.usuario',
            'matricula.turma.curso.templateCertificado',
            'matricula.turma.nivel',
            'matricula.curso.templateCertificado',
            'matricula.nivel',
            'matricula.historicos',
            'instituicao',
        ]);

        $matricula = $certificado->matricula;
        $dados = $this->elegibilidade->avaliar($matricula);

        $template = $idTemplate !== null
            ? $this->resolverTemplate->porId($idTemplate)
            : $this->resolverTemplate->doCurso($matricula);

        return DB::transaction(function () use ($certificado, $matricula, $dados, $template) {
            $fundoAnterior = $certificado->fundo_path;

            if ($template) {
                $conteudo = $this->placeholders->montar($template->conteudo, $certificado, $matricula);
                $fundo = $this->fundos->copiarParaSnapshot($template->fundo_path, $certificado->id);

                if ($fundoAnterior && $fundoAnterior !== $fundo) {
                    $this->fundos->remover($fundoAnterior);
                }

                $certificado->update([
                    'emitido_em' => now(),
                    'carga_horaria' => $dados['carga_horaria'],
                    'conteudo_gerado' => $conteudo,
                    'fundo_path' => $fundo,
                ]);
            } else {
                $this->fundos->remover($fundoAnterior);

                $certificado->update([
                    'emitido_em' => now(),
                    'carga_horaria' => $dados['carga_horaria'],
                    'conteudo_gerado' => null,
                    'fundo_path' => null,
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
}

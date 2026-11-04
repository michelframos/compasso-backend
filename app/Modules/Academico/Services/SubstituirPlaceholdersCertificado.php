<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Support\LayoutCertificado;
use Carbon\Carbon;

class SubstituirPlaceholdersCertificado
{
    /** @var list<string> */
    private const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    public function __construct(private readonly LayoutCertificadoRenderer $renderer) {}

    /**
     * Monta o HTML final do certificado a partir do layout (ou HTML legado).
     */
    public function montar(string $conteudo, Certificado $certificado, Matricula $matricula): string
    {
        $mapa = $this->mapa($certificado, $matricula);
        $layout = LayoutCertificado::parse($conteudo);

        foreach ($layout['elementos'] as &$elemento) {
            if (($elemento['tipo'] ?? '') === 'texto' && isset($elemento['texto'])) {
                $elemento['texto'] = str_replace(array_keys($mapa), array_values($mapa), (string) $elemento['texto']);
            }
        }
        unset($elemento);

        return $this->renderer->paraHtml($layout, true);
    }

    /** @return array<string, string> */
    private function mapa(Certificado $certificado, Matricula $matricula): array
    {
        $matricula->loadMissing([
            'aluno.usuario',
            'aluno.responsaveis.usuario',
            'turma.curso',
            'turma.nivel',
            'turma.professor.usuario',
            'turma.horarios',
            'curso',
            'nivel',
            'professor.usuario',
        ]);

        $aluno = $matricula->aluno;
        $usuario = $aluno?->usuario;
        $responsavel = $aluno?->responsaveis?->first()?->usuario;
        $turma = $matricula->turma;

        $cursoNome = $matricula->tipo === 'curso'
            ? ($matricula->curso?->nome ?? '')
            : ($turma?->curso?->nome ?? '');
        $nivelNome = $matricula->tipo === 'curso'
            ? ($matricula->nivel?->nome ?? '')
            : ($turma?->nivel?->nome ?? '');
        $professorNome = $matricula->tipo === 'curso'
            ? ($matricula->professor?->usuario?->nome ?? '')
            : ($turma?->professor?->usuario?->nome ?? '');

        $endereco = collect([
            $usuario?->rua,
            $usuario?->numero ? ', '.$usuario->numero : null,
            $usuario?->bairro ? ' - '.$usuario->bairro : null,
            $usuario?->complemento ? ' - '.$usuario->complemento : null,
            $usuario?->cep ? ' (CEP: '.$usuario->cep.')' : null,
        ])->filter()->implode('');

        return [
            '{{nome_aluno}}' => $usuario?->nome ?? '',
            '{{cpf_aluno}}' => $usuario?->cpf ?? '',
            '{{email_aluno}}' => $usuario?->email ?? '',
            '{{telefone_aluno}}' => $usuario?->telefone ?? '',
            '{{data_nascimento_aluno}}' => $this->data($usuario?->data_aniversario),
            '{{endereco_aluno}}' => $endereco,
            '{{nome_responsavel}}' => $responsavel?->nome ?? '',
            '{{cpf_responsavel}}' => $responsavel?->cpf ?? '',
            '{{nome_curso}}' => $cursoNome,
            '{{nome_nivel}}' => $nivelNome,
            '{{nome_turma}}' => $turma?->apelido() ?? '',
            '{{descricao_turma}}' => $turma?->descricao ?? '',
            '{{horarios_turma}}' => $this->horarios($turma),
            '{{nome_professor}}' => $professorNome,
            '{{data_matricula}}' => $this->data($matricula->data),
            '{{nome_escola}}' => $certificado->instituicao?->nome_fantasia ?? 'Escola',
            '{{carga_horaria}}' => number_format((float) $certificado->carga_horaria, 2, ',', '.'),
            '{{data_emissao}}' => $this->data($certificado->emitido_em),
            '{{codigo_verificacao}}' => $certificado->codigo_verificacao ?? '',
        ];
    }

    private function data(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function horarios(mixed $turma): string
    {
        if (! $turma || ! $turma->relationLoaded('horarios')) {
            return '';
        }

        return $turma->horarios
            ->map(function ($horario) {
                $dia = is_numeric($horario->dia_semana)
                    ? (self::DIAS[(int) $horario->dia_semana] ?? $horario->dia_semana)
                    : $horario->dia_semana;
                $inicio = substr((string) $horario->hora_inicio, 0, 5);
                $fim = substr((string) $horario->hora_termino, 0, 5);

                return trim("{$dia}: {$inicio} - {$fim}");
            })
            ->filter()
            ->implode(', ');
    }
}

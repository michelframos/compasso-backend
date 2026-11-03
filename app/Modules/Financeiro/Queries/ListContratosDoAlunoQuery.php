<?php

namespace App\Modules\Financeiro\Queries;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Financeiro\Models\AlunoContrato;
use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Pessoas\Models\Aluno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListContratosDoAlunoQuery
{
    public function build(int $idAluno): Collection
    {
        $aluno = Aluno::query()->with('usuario')->find($idAluno);
        $nomeAluno = $aluno?->usuario?->nome ?? 'Aluno';

        return collect()
            ->merge($this->avulsos($idAluno))
            ->merge($this->matriculas($idAluno))
            ->merge($this->instrumentos($idAluno))
            ->merge($this->espetaculos($idAluno, $nomeAluno))
            ->sortByDesc('ordenado_em')
            ->values()
            ->map(fn (array $item) => collect($item)->except('ordenado_em')->all());
    }

    public function resolver(int $idAluno, string $tipo, int $id): ?array
    {
        return $this->build($idAluno)->first(
            fn (array $item) => $item['tipo'] === $tipo && (int) $item['id'] === $id
        );
    }

    private function avulsos(int $idAluno): Collection
    {
        return AlunoContrato::query()
            ->with('contrato')
            ->where('aluno_id', $idAluno)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AlunoContrato $contrato) => [
                'tipo' => 'avulso',
                'id' => $contrato->id,
                'nome' => $contrato->contrato?->nome ?? 'Contrato',
                'descricao' => 'Contrato avulso',
                'contrato_gerado' => $contrato->contrato_gerado,
                'ordenado_em' => $this->timestamp($contrato->created_at) ?? $contrato->id,
            ]);
    }

    private function matriculas(int $idAluno): Collection
    {
        return Matricula::query()
            ->with(['turma.curso', 'curso', 'contrato'])
            ->where('id_aluno', $idAluno)
            ->whereNotNull('contrato_gerado')
            ->where('contrato_gerado', '!=', '')
            ->orderByDesc('id')
            ->get()
            ->map(function (Matricula $matricula) {
                $curso = $matricula->turma?->curso?->nome ?? $matricula->curso?->nome;
                $isCurso = $matricula->tipo === 'curso' || (! $matricula->id_turma && $matricula->id_curso);
                $titulo = $isCurso
                    ? ($curso ?: 'Curso')
                    : ($matricula->turma?->descricao ?: ($curso ? "Turma de {$curso}" : 'Matrícula'));

                return [
                    'tipo' => 'matricula',
                    'id' => $matricula->id,
                    'nome' => $matricula->contrato?->nome ?? 'Contrato de matrícula',
                    'descricao' => "Matrícula — {$titulo}",
                    'contrato_gerado' => $matricula->contrato_gerado,
                    'ordenado_em' => $this->timestamp($matricula->data) ?? $matricula->id,
                ];
            });
    }

    private function instrumentos(int $idAluno): Collection
    {
        return Instrumento::query()
            ->where('id_aluno', $idAluno)
            ->orderByDesc('id')
            ->get()
            ->map(function (Instrumento $instrumento) use ($idAluno) {
                $historico = $instrumento->historicos()
                    ->with('contrato')
                    ->where('id_aluno', $idAluno)
                    ->where('acao', 'emprestimo')
                    ->latest('data')
                    ->first();

                $conteudo = $historico?->contrato_gerado;
                if ($conteudo === null || $conteudo === '') {
                    return null;
                }

                $serie = $instrumento->numero_serie ? " • SN: {$instrumento->numero_serie}" : '';

                return [
                    'tipo' => 'instrumento',
                    'id' => $instrumento->id,
                    'nome' => $historico?->contrato?->nome ?? 'Termo de responsabilidade',
                    'descricao' => "Empréstimo — {$instrumento->nome}{$serie}",
                    'contrato_gerado' => $conteudo,
                    'ordenado_em' => $this->timestamp($instrumento->data_emprestimo)
                        ?? $this->timestamp($historico?->data)
                        ?? $instrumento->id,
                ];
            })
            ->filter()
            ->values();
    }

    private function espetaculos(int $idAluno, string $nomeAluno): Collection
    {
        return Espetaculo::query()
            ->with('contrato')
            ->whereNotNull('contrato_id')
            ->whereHas('apresentacoes.alunos', fn ($q) => $q->where('id_aluno', $idAluno))
            ->orderByDesc('data_evento')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Espetaculo $espetaculo) => filled($espetaculo->contrato?->conteudo))
            ->map(function (Espetaculo $espetaculo) use ($nomeAluno) {
                $data = $this->formatarData($espetaculo->data_evento);

                return [
                    'tipo' => 'espetaculo',
                    'id' => $espetaculo->id,
                    'nome' => $espetaculo->contrato?->nome ?? 'Contrato de espetáculo',
                    'descricao' => 'Espetáculo — '.$espetaculo->titulo,
                    'contrato_gerado' => $this->conteudoEspetaculo(
                        (string) $espetaculo->contrato->conteudo,
                        $nomeAluno,
                        $espetaculo->titulo,
                        $data,
                        $espetaculo->local,
                    ),
                    'ordenado_em' => $this->timestamp($espetaculo->data_evento) ?? $espetaculo->id,
                ];
            })
            ->values();
    }

    private function conteudoEspetaculo(
        string $conteudo,
        string $nomeAluno,
        ?string $titulo,
        ?string $data,
        ?string $local,
    ): string {
        $trocas = [
            '{{nome_aluno}}' => $nomeAluno,
            '{{nome_espetaculo}}' => $titulo ?? '',
            '{{data_espetaculo}}' => $data ?? '',
            '{{local_espetaculo}}' => $local ?? '',
            '[DATA_EVENTO]' => $data ?? '',
            '[ESPETACULO_TITULO]' => $titulo ?? '',
            '[LOCAL_EVENTO]' => $local ?? '',
        ];

        return str_replace(array_keys($trocas), array_values($trocas), $conteudo);
    }

    private function timestamp(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatarData(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Modules\Espetaculos\Adapters;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Contracts\ProximasApresentacoesAlunoPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\Models\Espetaculo;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class ProximasApresentacoesAlunoAdapter implements ProximasApresentacoesAlunoPort
{
    public function __construct(private readonly PlanoEntitlementResolverInterface $planos) {}

    public function listar(int $idAluno, int $limite = 5): array
    {
        if (! $this->moduloLiberado('espetaculos')) {
            return ['disponivel' => false, 'itens' => []];
        }

        $hoje = now()->toDateString();

        $itens = $this->apresentacoesDoAluno($idAluno)
            ->whereHas('espetaculo', fn (Builder $e) => $e
                ->whereDate('data_evento', '>=', $hoje)
                ->whereNotIn('status', Espetaculo::STATUS_ENCERRADOS))
            ->get()
            ->sortBy(fn (Apresentacao $apresentacao) => sprintf(
                '%s-%06d',
                $this->data($apresentacao->espetaculo?->data_evento),
                $apresentacao->id
            ))
            ->take($limite)
            ->map(fn (Apresentacao $apresentacao) => [
                'id' => $apresentacao->id,
                'titulo_musica' => $apresentacao->titulo_musica ?: 'Apresentação',
                'espetaculo' => $apresentacao->espetaculo?->titulo ?? 'Espetáculo',
                'data_evento' => $this->data($apresentacao->espetaculo?->data_evento),
                'local' => $apresentacao->espetaculo?->local,
            ])
            ->values()
            ->all();

        return ['disponivel' => true, 'itens' => $itens];
    }

    public function noPeriodo(int $idAluno, string $inicio, string $fim): array
    {
        if (! $this->moduloLiberado('espetaculos')) {
            return ['disponivel' => false, 'apresentacoes' => [], 'ensaios' => []];
        }

        $apresentacoes = $this->apresentacoesDoAluno($idAluno)
            ->whereHas('espetaculo', fn (Builder $e) => $e->whereBetween('data_evento', [$inicio, $fim]))
            ->get()
            ->sortBy(fn (Apresentacao $apresentacao) => $this->data($apresentacao->espetaculo?->data_evento).'-'.sprintf('%06d', $apresentacao->id))
            ->map(fn (Apresentacao $apresentacao) => [
                'id' => $apresentacao->id,
                'titulo_musica' => $apresentacao->titulo_musica ?: 'Apresentação',
                'espetaculo' => $apresentacao->espetaculo?->titulo ?? 'Espetáculo',
                'data_evento' => $this->data($apresentacao->espetaculo?->data_evento),
                'local' => $apresentacao->espetaculo?->local,
                'status' => $apresentacao->espetaculo?->status,
            ])
            ->values()
            ->all();

        $ensaios = Ensaio::query()
            ->whereBetween('data', [$inicio, $fim])
            ->whereHas('apresentacao', fn (Builder $apresentacao) => $this->restringirAoAluno($apresentacao, $idAluno))
            ->with('apresentacao.espetaculo')
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (Ensaio $ensaio) => [
                'id' => $ensaio->id,
                'data' => $this->data($ensaio->data),
                'hora_inicio' => substr((string) $ensaio->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $ensaio->hora_termino, 0, 5),
                'local' => $ensaio->local,
                'titulo_musica' => $ensaio->apresentacao?->titulo_musica ?: 'Apresentação',
                'espetaculo' => $ensaio->apresentacao?->espetaculo?->titulo ?? 'Espetáculo',
            ])
            ->all();

        return ['disponivel' => true, 'apresentacoes' => $apresentacoes, 'ensaios' => $ensaios];
    }

    private function apresentacoesDoAluno(int $idAluno): Builder
    {
        return $this->restringirAoAluno(Apresentacao::query()->with('espetaculo'), $idAluno);
    }

    private function restringirAoAluno(Builder $query, int $idAluno): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('alunos', fn (Builder $a) => $a->where('id_aluno', $idAluno))
            ->orWhereHas('turma.matriculas', fn (Builder $m) => $m->where('id_aluno', $idAluno)->vigentes()));
    }

    private function data(mixed $valor): string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return substr((string) $valor, 0, 10);
    }

    private function moduloLiberado(string $modulo): bool
    {
        $instituicao = InstituicaoContext::instituicao();

        return $instituicao !== null && $this->planos->permiteModulo($instituicao, $modulo);
    }
}

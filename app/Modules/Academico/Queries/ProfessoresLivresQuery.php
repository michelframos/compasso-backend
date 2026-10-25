<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\DisponibilidadeProfessor;
use App\Modules\Pessoas\Models\Professor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Professores da instituição com a situação de agenda num horário: livres primeiro, depois fora da disponibilidade, por fim com conflito. */
class ProfessoresLivresQuery
{
    /**
     * @return Collection<int, array{id: int, nome: ?string, conflito: bool, fora_disponibilidade: bool}>
     */
    public function build(string $data, string $inicio, string $termino, array $ignorarAulas = []): Collection
    {
        $inicio = substr($inicio, 0, 5).':00';
        $termino = substr($termino, 0, 5).':00';

        $ocupados = AulaTurma::query()
            ->where('status', '!=', 'cancelada')
            ->whereDate('data', $data)
            ->where('hora_inicio', '<', $termino)
            ->where('hora_termino', '>', $inicio)
            ->when($ignorarAulas !== [], fn ($q) => $q->whereNotIn('id', $ignorarAulas))
            ->pluck('id_professor')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $dia = DisponibilidadeProfessor::diaDa(Carbon::parse($data));
        $janelas = DisponibilidadeProfessor::query()->get()->groupBy('id_professor');

        return Professor::query()
            ->with('usuario:id,nome')
            ->get()
            ->map(function (Professor $professor) use ($ocupados, $janelas, $dia, $inicio, $termino): array {
                $doProfessor = $janelas->get($professor->id);
                $fora = $doProfessor !== null && ! $doProfessor->contains(
                    fn (DisponibilidadeProfessor $j) => $j->dia_semana === $dia
                        && substr($j->hora_inicio, 0, 5).':00' <= $inicio
                        && substr($j->hora_termino, 0, 5).':00' >= $termino
                );

                return [
                    'id' => $professor->id,
                    'nome' => $professor->usuario?->nome,
                    'conflito' => $ocupados->has($professor->id),
                    'fora_disponibilidade' => $fora,
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => $a['conflito'] <=> $b['conflito'],
                fn (array $a, array $b) => $a['fora_disponibilidade'] <=> $b['fora_disponibilidade'],
                fn (array $a, array $b) => strcasecmp((string) $a['nome'], (string) $b['nome']),
            ])
            ->values();
    }
}

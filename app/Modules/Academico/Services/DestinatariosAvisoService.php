<?php

namespace App\Modules\Academico\Services;

use App\Models\Aluno;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Contracts\EnviarAvisoTurmaPort;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\NumeroWhatsapp;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Decide quem recebe um aviso e por qual contato. */
class DestinatariosAvisoService
{
    /**
     * A turma inteira (matrículas vigentes) ou os alunos escolhidos, sempre dentro do que o usuário enxerga.
     *
     * @param  list<int>  $idsAlunos
     * @return Collection<int, Aluno>
     */
    public function alunos(User $user, ?Turma $turma, array $idsAlunos): Collection
    {
        $permitidos = Matricula::query()
            ->visivelPara($user)
            ->vigentes()
            ->when($turma, fn ($q) => $q->where('id_turma', $turma->id))
            ->distinct()
            ->pluck('id_aluno')
            ->map(fn ($id) => (int) $id);

        $ids = $permitidos;
        if ($idsAlunos !== []) {
            if (array_diff($idsAlunos, $permitidos->all()) !== []) {
                throw ValidationException::withMessages([
                    'ids_alunos' => $turma
                        ? 'Há alunos escolhidos que não estão matriculados nesta turma.'
                        : 'Há alunos escolhidos que você não pode avisar.',
                ]);
            }
            $ids = collect($idsAlunos);
        }

        return $this->carregar($ids->unique()->values()->all());
    }

    /** @return Collection<int, Aluno> */
    public function alunosDaAula(AulaTurma $aula): Collection
    {
        if ($aula->id_aluno_especifico) {
            return $this->carregar([(int) $aula->id_aluno_especifico]);
        }

        if (! $aula->id_turma) {
            return collect();
        }

        $ids = Matricula::query()->where('id_turma', $aula->id_turma)->vigentes()->distinct()->pluck('id_aluno')->all();

        return $this->carregar($ids);
    }

    /**
     * Uma linha por pessoa e canal, sem repetir quem é responsável por mais de um aluno.
     *
     * @param  Collection<int, Aluno>  $alunos
     * @param  list<string>  $canais
     * @return list<array{id_aluno: int, id_usuario: int, tipo: string, nome: string, canal: string, destino: ?string}>
     */
    public function montar(Collection $alunos, string $publico, array $canais): array
    {
        $linhas = [];
        foreach ($alunos as $aluno) {
            foreach ($canais as $canal) {
                foreach ($this->pessoas($aluno, $publico, $canal) as [$tipo, $usuario]) {
                    $linhas[$usuario->id.'|'.$canal] ??= [
                        'id_aluno' => $aluno->id,
                        'id_usuario' => $usuario->id,
                        'tipo' => $tipo,
                        'nome' => $usuario->nome,
                        'canal' => $canal,
                        'destino' => $this->destino($usuario, $canal),
                    ];
                }
            }
        }

        return array_values($linhas);
    }

    public function destino(User $usuario, string $canal): ?string
    {
        if ($canal === EnviarAvisoTurmaPort::WHATSAPP) {
            return NumeroWhatsapp::formatar($usuario->whatsapp ?: $usuario->telefone);
        }

        return $usuario->email ?: null;
    }

    /**
     * Quem recebe por este aluno neste canal. Aluno sem contato no canal é avisado pelos responsáveis;
     * aluno sem responsável (adulto) recebe mesmo quando o público é "responsáveis".
     *
     * @return list<array{0: string, 1: User}>
     */
    private function pessoas(Aluno $aluno, string $publico, string $canal): array
    {
        $proprio = $aluno->usuario ? [[AvisoTurmaDestinatario::TIPO_ALUNO, $aluno->usuario]] : [];
        $responsaveis = $aluno->responsaveis
            ->map(fn ($responsavel) => $responsavel->usuario)
            ->filter()
            ->map(fn (User $usuario) => [AvisoTurmaDestinatario::TIPO_RESPONSAVEL, $usuario])
            ->values()
            ->all();

        return match ($publico) {
            AvisoTurma::PUBLICO_AMBOS => [...$proprio, ...$responsaveis],
            AvisoTurma::PUBLICO_RESPONSAVEIS => $responsaveis ?: $proprio,
            default => $responsaveis === [] || ($aluno->usuario && $this->destino($aluno->usuario, $canal))
                ? $proprio
                : $responsaveis,
        };
    }

    /** @param  list<int>  $ids */
    private function carregar(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Aluno::query()
            ->whereIn('id', $ids)
            ->with(['usuario', 'responsaveis.usuario'])
            ->get();
    }
}

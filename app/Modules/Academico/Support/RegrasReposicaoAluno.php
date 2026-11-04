<?php

namespace App\Modules\Academico\Support;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Services\ClassSchedulingService;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegrasReposicaoAluno
{
    public function __construct(private readonly ClassSchedulingService $agenda) {}

    public function aulaPerdida(int $idAluno, int $idAula): AulaTurma
    {
        $aula = AulaTurma::query()->with('turma')->whereKey($idAula)->first();

        if ($aula === null) {
            throw new HttpException(404, 'Aula não encontrada.');
        }

        $permitida = AulaTurma::query()
            ->whereKey($idAula)
            ->where(fn ($q) => $q
                ->where('id_aluno_especifico', $idAluno)
                ->orWhere(fn ($turma) => $turma
                    ->whereNull('id_aluno_especifico')
                    ->whereHas('turma.matriculas', fn ($m) => $m->where('id_aluno', $idAluno)->vigentes())))
            ->exists();

        if (! $permitida) {
            throw new HttpException(403, 'Acesso restrito: esta aula não é sua.');
        }

        if ($aula->status === 'cancelada') {
            throw ValidationException::withMessages([
                'id_aula_turma_perdida' => 'Não é possível repor uma aula cancelada.',
            ]);
        }

        $falta = AulaPresenca::query()
            ->where('id_aula_turma', $aula->id)
            ->where('id_aluno', $idAluno)
            ->where('status', 'ausente')
            ->exists();

        if (! $falta) {
            throw ValidationException::withMessages([
                'id_aula_turma_perdida' => 'Só é possível repor uma falta já registrada.',
            ]);
        }

        return $aula;
    }

    public function validarPrazo(AulaTurma $aula): void
    {
        $prazo = $this->instituicao()?->prazo_reposicao_dias;

        if ($prazo === null) {
            return;
        }

        $limite = $aula->data->copy()->startOfDay()->addDays((int) $prazo);

        if (now()->startOfDay()->gt($limite)) {
            throw ValidationException::withMessages([
                'id_aula_turma_perdida' => 'O prazo para pedir reposição desta falta já passou.',
            ]);
        }
    }

    public function validarLimite(int $idAluno): void
    {
        $limite = $this->instituicao()?->limite_reposicoes_periodo;

        if ($limite === null) {
            return;
        }

        $usados = SolicitacaoReposicao::query()
            ->where('id_aluno', $idAluno)
            ->whereIn('status', SolicitacaoReposicao::ABERTAS)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($usados >= (int) $limite) {
            throw ValidationException::withMessages([
                'id_aula_turma_perdida' => 'Você atingiu o limite de reposições deste mês.',
            ]);
        }
    }

    public function validarDestino(int $idAluno, AulaTurma $perdida, AulaTurma $destino): void
    {
        $erro = $this->erroDoDestino($idAluno, $perdida, $destino);

        if ($erro !== null) {
            throw ValidationException::withMessages(['id_aula_turma_destino' => $erro]);
        }
    }

    public function erroDoDestino(int $idAluno, AulaTurma $perdida, AulaTurma $destino): ?string
    {
        $destino->loadMissing('turma');

        if ($destino->id === $perdida->id) {
            return 'Escolha outra aula para repor a falta.';
        }

        if ($destino->status !== 'agendada' || $destino->data->toDateString() < now()->toDateString()) {
            return 'Escolha uma aula futura que ainda não aconteceu.';
        }

        if (! $this->mesmoCurso($perdida, $destino)) {
            return 'A reposição precisa ser do mesmo curso.';
        }

        if ($this->jaEhDoAluno($destino, $idAluno)) {
            return 'Esta aula já é sua.';
        }

        if (! $this->agenda->temVaga($destino, $idAluno)) {
            return 'Esta aula não tem vaga para reposição.';
        }

        $conflito = $this->agenda->conflitosDoAluno(
            $idAluno,
            $destino->data->toDateString(),
            (string) $destino->hora_inicio,
            (string) $destino->hora_termino,
            [$destino->id]
        );

        if ($conflito->isNotEmpty()) {
            return 'Você já tem aula neste horário.';
        }

        return null;
    }

    public function mesmoCurso(AulaTurma $perdida, AulaTurma $destino): bool
    {
        $perdida->loadMissing('turma');
        $destino->loadMissing('turma');
        $origem = $perdida->id_curso ?: $perdida->turma?->id_curso;
        $alvo = $destino->id_curso ?: $destino->turma?->id_curso;

        return $origem !== null && (int) $origem === (int) $alvo;
    }

    public function usadosNoPeriodo(int $idAluno): int
    {
        return SolicitacaoReposicao::query()
            ->where('id_aluno', $idAluno)
            ->whereIn('status', SolicitacaoReposicao::ABERTAS)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    private function jaEhDoAluno(AulaTurma $aula, int $idAluno): bool
    {
        if ((int) $aula->id_aluno_especifico === $idAluno) {
            return true;
        }

        if ($aula->id_aluno_especifico !== null) {
            return false;
        }

        return (bool) $aula->turma?->matriculas()->where('id_aluno', $idAluno)->vigentes()->exists();
    }

    private function instituicao(): ?Instituicao
    {
        $id = InstituicaoContext::id();

        return $id === null ? null : Instituicao::query()->find($id);
    }
}

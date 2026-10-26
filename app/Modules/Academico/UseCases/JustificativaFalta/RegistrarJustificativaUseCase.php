<?php

namespace App\Modules\Academico\UseCases\JustificativaFalta;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrarJustificativaUseCase
{
    /**
     * @param  array{id_aula_turma: int, tipo: string, motivo: string}  $dados
     */
    public function execute(int $idAluno, array $dados, ?UploadedFile $anexo): JustificativaFalta
    {
        $aula = $this->aulaDoAluno($idAluno, (int) $dados['id_aula_turma']);
        $this->validarTipo($aula, $idAluno, $dados['tipo']);
        $this->validarDuplicidade($idAluno, $aula->id);

        $justificativa = DB::transaction(function () use ($idAluno, $aula, $dados) {
            return JustificativaFalta::create([
                'id_aluno' => $idAluno,
                'id_aula_turma' => $aula->id,
                'tipo' => $dados['tipo'],
                'motivo' => $dados['motivo'],
                'status' => JustificativaFalta::STATUS_PENDENTE,
            ]);
        });

        if ($anexo !== null) {
            $caminho = $anexo->store('justificativas/'.InstituicaoContext::id(), 'public');
            $justificativa->update(['anexo' => $caminho]);
        }

        return $justificativa->fresh();
    }

    private function aulaDoAluno(int $idAluno, int $idAula): AulaTurma
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
            throw ValidationException::withMessages(['id_aula_turma' => 'Não é possível avisar ou justificar uma aula cancelada.']);
        }

        return $aula;
    }

    private function validarTipo(AulaTurma $aula, int $idAluno, string $tipo): void
    {
        if ($tipo === JustificativaFalta::TIPO_AVISO) {
            if ($aula->status === 'concluida' || $aula->data->toDateString() < now()->toDateString()) {
                throw ValidationException::withMessages([
                    'tipo' => 'O aviso de ausência é para uma aula que ainda vai acontecer.',
                ]);
            }

            return;
        }

        $falta = AulaPresenca::query()
            ->where('id_aula_turma', $aula->id)
            ->where('id_aluno', $idAluno)
            ->where('status', 'ausente')
            ->exists();

        if (! $falta) {
            throw ValidationException::withMessages([
                'tipo' => 'Só é possível justificar uma falta já registrada.',
            ]);
        }
    }

    private function validarDuplicidade(int $idAluno, int $idAula): void
    {
        $aberta = JustificativaFalta::query()
            ->where('id_aluno', $idAluno)
            ->where('id_aula_turma', $idAula)
            ->whereIn('status', [JustificativaFalta::STATUS_PENDENTE, JustificativaFalta::STATUS_ACEITA])
            ->exists();

        if ($aberta) {
            throw ValidationException::withMessages([
                'id_aula_turma' => 'Já existe um pedido pendente ou aceito para esta aula.',
            ]);
        }
    }
}

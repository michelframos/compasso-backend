<?php

namespace App\Modules\Academico\Observers;

use App\Modules\Academico\Models\Turma;
use App\Enums\TurmaStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TurmaObserver
{
    /**
     * Handle the Turma "updating" event.
     */
    public function updating(Turma $turma): void
    {
        // Se a turma estiver concluída ou cancelada no banco (estado original),
        // bloquear edições (exceto a própria mudança de status)
        $statusOriginal = $turma->getOriginal('status');

        if ($statusOriginal === TurmaStatus::CONCLUIDA || $statusOriginal === TurmaStatus::CANCELADA) {
            // Permitir se a ÚNICA alteração for o próprio status via PATCH
            $dirty = $turma->getDirty();
            if (count($dirty) === 1 && array_key_exists('status', $dirty)) {
                return; // Liberado mudar o status de Cancelada de volta para Aberta, etc (se a regra permitir)
            }

            throw new HttpException(403, "Não é possível alterar informações de uma turma ({$statusOriginal->value}).");
        }
    }

    /**
     * Handle the Turma "deleting" event.
     */
    public function deleting(Turma $turma): void
    {
        $statusAtual = $turma->status;

        if ($statusAtual === TurmaStatus::CONCLUIDA || $statusAtual === TurmaStatus::CANCELADA) {
            // Verificar se existem aulas associadas a esta turma.
            // O nome do relacionamento pode ser 'aulas', dependendo do model Turma.
            // Aqui assumimos que seria $turma->aulas()->count() ou consulta direta.

            $temAulas = \Illuminate\Support\Facades\DB::table('aulas_turmas')
                          ->where('id_turma', $turma->id)
                          ->where('id_instituicao', $turma->id_instituicao)
                          ->exists();

            if ($temAulas) {
                throw new HttpException(403, "Proibido excluir turma '{$statusAtual->value}' que já possui histórico de aulas dadas/agendadas.");
            }
        }
    }
}

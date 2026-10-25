<?php

namespace App\Modules\Academico\Listeners;

use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use App\Modules\Core\Events\NotificacaoProcessada;

class AtualizarDestinatarioAviso
{
    public function handle(NotificacaoProcessada $evento): void
    {
        if ($evento->referenciaType !== AvisoTurmaDestinatario::class) {
            return;
        }

        $enviado = $evento->status === NotificacaoProcessada::ENVIADO;

        AvisoTurmaDestinatario::withoutInstituicaoScope()
            ->whereKey($evento->referenciaId)
            ->update([
                'status' => $enviado ? AvisoTurmaDestinatario::STATUS_ENVIADO : AvisoTurmaDestinatario::STATUS_ERRO,
                'erro' => $enviado ? null : mb_substr((string) $evento->erro, 0, 255),
                'enviado_em' => $enviado ? now() : null,
            ]);
    }
}

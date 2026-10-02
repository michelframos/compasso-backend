<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\LimiteAvisosProfessor;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Só professores têm limite; avisos automáticos de alteração de aula não contam. */
class LimiteAvisosService
{
    /** @return array{por_dia: int, enviados_hoje: int, restantes: int}|null */
    public function situacao(User $user): ?array
    {
        if ($user->role !== 'professor') {
            return null;
        }

        $porDia = LimiteAvisosProfessor::da(Instituicao::query()->find(InstituicaoContext::id()));
        $enviados = AvisoTurma::query()
            ->where('id_usuario_autor', $user->id)
            ->where('origem', AvisoTurma::ORIGEM_MANUAL)
            ->where('created_at', '>=', today())
            ->count();

        return ['por_dia' => $porDia, 'enviados_hoje' => $enviados, 'restantes' => max(0, $porDia - $enviados)];
    }

    public function garantir(User $user): void
    {
        $situacao = $this->situacao($user);

        if ($situacao !== null && $situacao['restantes'] === 0) {
            throw new HttpResponseException(response()->json([
                'message' => "Você atingiu o limite de {$situacao['por_dia']} avisos por dia definido pela escola. Tente novamente amanhã.",
            ], 429));
        }
    }
}

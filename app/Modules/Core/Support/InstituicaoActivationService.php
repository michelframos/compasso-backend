<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;
use Illuminate\Support\Facades\Hash;

/**
 * Código de ativação exigido no primeiro acesso de escolas cadastradas pela tela de login.
 */
class InstituicaoActivationService
{
    public const VALIDADE_DIAS = 7;

    /**
     * Gera um novo código, grava o hash na instituição e devolve o código em texto puro.
     */
    public function gerarCodigo(Instituicao $instituicao): string
    {
        $codigo = sprintf('%06d', random_int(0, 999999));

        $instituicao->forceFill([
            'codigo_ativacao' => Hash::make($codigo),
            'codigo_ativacao_expira_em' => now()->addDays(self::VALIDADE_DIAS),
            'ativada_em' => null,
        ])->save();

        return $codigo;
    }

    public function codigoValido(Instituicao $instituicao, string $codigo): bool
    {
        if (! $instituicao->aguardandoAtivacao()) {
            return false;
        }

        if ($instituicao->codigo_ativacao_expira_em !== null && $instituicao->codigo_ativacao_expira_em->isPast()) {
            return false;
        }

        return Hash::check(trim($codigo), $instituicao->codigo_ativacao);
    }

    public function ativar(Instituicao $instituicao): void
    {
        $instituicao->forceFill([
            'codigo_ativacao' => null,
            'codigo_ativacao_expira_em' => null,
            'ativada_em' => now(),
        ])->save();
    }
}

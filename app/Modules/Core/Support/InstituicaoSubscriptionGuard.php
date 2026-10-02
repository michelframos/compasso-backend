<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;

class InstituicaoSubscriptionGuard
{
    public function isBlocked(Instituicao $instituicao): bool
    {
        return $this->check($instituicao) !== null;
    }

    /**
     * @return array{message: string, code: string, trial_ends_at: ?string}|null
     */
    public function check(Instituicao $instituicao): ?array
    {
        if ($instituicao->status === Instituicao::STATUS_SUSPENSO) {
            return $this->blockedResponse(
                'Esta instituição está suspensa. Entre em contato com o suporte.',
                'institution_suspended',
                $instituicao,
            );
        }

        if ($instituicao->assinatura_status === Instituicao::ASSINATURA_CANCELED) {
            return $this->blockedResponse(
                'A assinatura desta instituição foi cancelada.',
                'subscription_canceled',
                $instituicao,
            );
        }

        if ($this->trialExpiradoSemPlanoAtivo($instituicao)) {
            return $this->blockedResponse(
                'O período de gratuidade expirou. Regularize a assinatura para continuar.',
                'subscription_inactive',
                $instituicao,
            );
        }

        return null;
    }

    private function trialExpiradoSemPlanoAtivo(Instituicao $instituicao): bool
    {
        if (! $this->trialExpirado($instituicao)) {
            return false;
        }

        return ! in_array($instituicao->assinatura_status, [
            Instituicao::ASSINATURA_ACTIVE,
            Instituicao::ASSINATURA_PAST_DUE,
        ], true);
    }

    public function emTrial(Instituicao $instituicao): bool
    {
        return $instituicao->assinatura_status === Instituicao::ASSINATURA_TRIALING
            && $instituicao->trial_ends_at !== null
            && $instituicao->trial_ends_at->isFuture();
    }

    public function trialExpirado(Instituicao $instituicao): bool
    {
        return $instituicao->trial_ends_at !== null
            && $instituicao->trial_ends_at->isPast();
    }

    /**
     * @return array{message: string, code: string, trial_ends_at: ?string}
     */
    private function blockedResponse(string $message, string $code, Instituicao $instituicao): array
    {
        return [
            'message' => $message,
            'code' => $code,
            'trial_ends_at' => $instituicao->trial_ends_at?->toIso8601String(),
        ];
    }
}

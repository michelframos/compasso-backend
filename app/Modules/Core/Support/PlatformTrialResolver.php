<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;
use Carbon\Carbon;

class PlatformTrialResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveForCreate(array $data): array
    {
        $data['status'] ??= Instituicao::STATUS_ATIVO;
        $data['assinatura_status'] ??= Instituicao::ASSINATURA_TRIALING;

        return $this->applyTrialFields($data, isCreate: true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveForUpdate(Instituicao $instituicao, array $data): array
    {
        if (! $this->hasTrialInput($data)) {
            unset($data['trial_dias']);

            return $data;
        }

        return $this->applyTrialFields($data, isCreate: false, instituicao: $instituicao);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasTrialInput(array $data): bool
    {
        return array_key_exists('trial_ends_at', $data)
            || array_key_exists('trial_dias', $data)
            || array_key_exists('usar_trial_padrao', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyTrialFields(array $data, bool $isCreate, ?Instituicao $instituicao = null): array
    {
        if (($data['usar_trial_padrao'] ?? false) === true) {
            $data['trial_ends_at'] = now()->addDays($this->defaultTrialDays());
            $data['trial_usa_padrao'] = true;
            unset($data['trial_dias'], $data['usar_trial_padrao']);

            return $data;
        }

        unset($data['usar_trial_padrao']);

        if (! empty($data['trial_ends_at'])) {
            $data['trial_ends_at'] = Carbon::parse($data['trial_ends_at']);
            $data['trial_usa_padrao'] = false;
            unset($data['trial_dias']);

            return $data;
        }

        if (isset($data['trial_dias']) && $data['trial_dias'] !== null && $data['trial_dias'] !== '') {
            $data['trial_ends_at'] = now()->addDays((int) $data['trial_dias']);
            $data['trial_usa_padrao'] = false;
            unset($data['trial_dias']);

            return $data;
        }

        unset($data['trial_dias']);

        if ($isCreate) {
            $data['trial_ends_at'] = now()->addDays($this->defaultTrialDays());
            $data['trial_usa_padrao'] = true;
        }

        return $data;
    }

    public function defaultTrialDays(): int
    {
        return (int) config('platform.default_trial_days', 14);
    }
}

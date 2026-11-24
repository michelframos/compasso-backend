<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\Ingresso;
use Illuminate\Support\Facades\DB;

class UtilizarIngressoUseCase
{
    public function __construct(private readonly ValidarIngressoUseCase $validar) {}

    /**
     * @return array{ok: bool, motivo?: string, ingresso?: Ingresso}
     */
    public function execute(?string $numero = null, ?string $codigoQr = null): array
    {
        return DB::transaction(function () use ($numero, $codigoQr) {
            $resultado = $this->validar->execute($numero, $codigoQr);

            if (! $resultado['ok']) {
                return $resultado;
            }

            /** @var Ingresso $ingresso */
            $ingresso = Ingresso::query()->lockForUpdate()->findOrFail($resultado['ingresso']->id);

            if ($ingresso->status === Ingresso::STATUS_UTILIZADO) {
                return [
                    'ok' => false,
                    'motivo' => 'O ingresso já foi utilizado.',
                    'ingresso' => $ingresso,
                ];
            }

            $ingresso->update([
                'status' => Ingresso::STATUS_UTILIZADO,
                'utilizado_em' => now(),
            ]);

            return [
                'ok' => true,
                'ingresso' => $ingresso->fresh(['espetaculo']),
            ];
        });
    }
}

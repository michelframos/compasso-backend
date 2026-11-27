<?php

namespace App\Modules\Core\Http\Concerns;

use App\Modules\Core\Exceptions\BillingGatewayException;
use Illuminate\Http\JsonResponse;

trait RespondsBillingGatewayErrors
{
    /**
     * Converte falhas do centralizador em resposta JSON (422 para regras de negócio, 502 para indisponibilidade).
     */
    protected function billingGatewayError(BillingGatewayException $e): JsonResponse
    {
        $status = in_array($e->httpStatus(), [422], true) ? 422 : 502;

        return response()->json(array_filter([
            'message' => $e->getMessage(),
            'errors' => $e->errors(),
        ], fn ($v) => $v !== null), $status);
    }
}

<?php

namespace App\Modules\Core\Support;

use Illuminate\Http\JsonResponse;

/**
 * Respostas JSON padronizadas para controllers de módulos.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        $payload = [];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, string $message = 'Criado com sucesso.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}

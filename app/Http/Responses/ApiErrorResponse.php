<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
    public static function make(
        string $code,
        string $message,
        array $details,
        int $status,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}

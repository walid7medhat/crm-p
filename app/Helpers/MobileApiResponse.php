<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * JSON envelope for /api/v1/mobile/* — additive; does not replace ApiResponse for web.
 */
class MobileApiResponse
{
    public static function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        $payload = is_array($data) ? $data : ['value' => $data];

        return response()->json([
            'status' => true,
            'message' => $message,
            'version' => config('mobile-api.version', 'v1'),
            'data' => array_merge(
                $payload,
                ['server_time' => now()->toIso8601String()]
            ),
        ], $code);
    }

    public static function error(string $message, int $code = 400, ?array $errors = null, ?array $extra = null): JsonResponse
    {
        $body = [
            'status' => false,
            'message' => $message,
            'version' => config('mobile-api.version', 'v1'),
            'data' => array_merge(
                $extra ?? [],
                ['server_time' => now()->toIso8601String()]
            ),
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $code);
    }

    public static function conflict(string $message, array $data = []): JsonResponse
    {
        return self::error($message, 409, null, $data);
    }
}

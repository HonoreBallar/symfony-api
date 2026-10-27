<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Builds the API envelopes for code that has no access to AbstractController::json()
 * (event listeners, security handlers). Data must already be normalized.
 */
final class ApiResponse
{
    public const int JSON_OPTIONS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    public static function success(mixed $data, string $message, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return self::create(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public static function error(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        return self::create(['success' => false, 'message' => $message, 'errors' => $errors], $status, $headers);
    }

    private static function create(array $payload, int $status, array $headers = []): JsonResponse
    {
        return (new JsonResponse($payload, $status, $headers))->setEncodingOptions(self::JSON_OPTIONS);
    }
}

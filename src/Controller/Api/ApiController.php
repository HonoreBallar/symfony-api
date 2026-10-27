<?php

namespace App\Controller\Api;

use App\Http\ApiResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps every successful API response in the { success, message, data, pagination? } envelope.
 */
abstract class ApiController extends AbstractController
{
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, $context + ['json_encode_options' => ApiResponse::JSON_OPTIONS]);
    }

    protected function success(mixed $data, string $message, int $status = Response::HTTP_OK, array $headers = [], array $context = []): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status, $headers, $context);
    }

    protected function paginated(Paginator $paginator, int $page, int $pageSize, string $message, array $context = []): JsonResponse
    {
        $totalItems = \count($paginator);

        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => iterator_to_array($paginator, false),
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalItems' => $totalItems,
                'totalPages' => (int) ceil($totalItems / $pageSize),
            ],
        ], Response::HTTP_OK, [], $context);
    }
}

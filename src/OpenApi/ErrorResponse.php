<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documents a { success: false, message, errors } response.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ErrorResponse extends OA\Response
{
    public function __construct(int $response, string $description)
    {
        parent::__construct(response: $response, description: $description, content: new OA\JsonContent(ref: '#/components/schemas/Error'));
    }
}

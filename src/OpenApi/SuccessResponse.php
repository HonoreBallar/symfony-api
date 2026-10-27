<?php

namespace App\OpenApi;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Documents a { success, message, data, pagination? } response.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SuccessResponse extends OA\Response
{
    /**
     * @param class-string|null $model
     * @param list<string>      $groups
     */
    public function __construct(int $response, string $description, ?string $model = null, array $groups = [], bool $paginated = false)
    {
        $item = null !== $model ? new Model(type: $model, groups: $groups ?: null) : null;

        $properties = [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'message', type: 'string'),
            match (true) {
                null === $item => new OA\Property(property: 'data', type: 'null', nullable: true),
                $paginated => new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: $item)),
                default => new OA\Property(property: 'data', ref: $item),
            },
        ];

        if ($paginated) {
            $properties[] = new OA\Property(property: 'pagination', ref: '#/components/schemas/Pagination');
        }

        parent::__construct(response: $response, description: $description, content: new OA\JsonContent(properties: $properties));
    }
}

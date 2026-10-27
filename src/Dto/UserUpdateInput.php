<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update of a user by an admin: only the fields sent are changed.
 */
final readonly class UserUpdateInput
{
    /**
     * @param list<string>|null $roles
     */
    public function __construct(
        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,

        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Choice(choices: User::ROLES)])]
        public ?array $roles = null,

        public ?bool $isActive = null,
    ) {
    }
}

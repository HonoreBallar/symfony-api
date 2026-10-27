<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Account created by an admin: ROLE_USER unless other roles are given.
 */
final readonly class UserCreateInput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        public string $password,

        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Choice(choices: User::ROLES)])]
        public array $roles = [User::ROLE_USER],
    ) {
    }
}

<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterInput
{
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
    ) {
    }
}

<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ForgotPasswordInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}

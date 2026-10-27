<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks unconfirmed and disabled accounts, both at login and on every request authenticated with a token.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException("Confirmez votre adresse e-mail avant de vous connecter : consultez l'e-mail envoyé lors de l'inscription.");
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Ce compte a été désactivé. Contactez un administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Nothing to check once the password has been verified.
    }
}

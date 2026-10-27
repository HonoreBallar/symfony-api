<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Account lifecycle shared by self-registration and admin creation:
 * create the account and send the confirmation link, then welcome the user once the e-mail is confirmed.
 */
final readonly class UserRegistration
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private AuthMailer $mailer,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function register(string $name, string $email, string $plainPassword, array $roles = [User::ROLE_USER]): User
    {
        if (null !== $this->users->findOneByEmail($email)) {
            throw new ConflictHttpException('Un compte existe déjà avec cette adresse e-mail.');
        }

        $user = (new User($name, $email))->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        // The mail is only queued (sent by the Messenger worker), after the flush so it never targets an account that was not saved.
        $this->em->persist($user);
        $this->em->flush();
        $this->mailer->sendEmailVerification($user);

        return $user;
    }

    /**
     * Only records the confirmation: never re-enables an account disabled by an admin.
     * The welcome e-mail is sent on the first confirmation only, so replaying the link does not resend it.
     */
    public function confirmEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }

        $user->markEmailAsVerified();
        $this->em->flush();

        $this->mailer->sendWelcome($user);
    }
}

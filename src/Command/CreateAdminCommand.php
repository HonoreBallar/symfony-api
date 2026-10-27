<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates (or promotes) an active admin account, e.g. to bootstrap the first admin.
 */
#[AsCommand(name: 'app:create-admin', description: 'Create an active admin user (or promote an existing one)')]
final readonly class CreateAdminCommand
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Admin e-mail')] string $email,
        #[Argument('Admin name')] string $name = 'Admin',
        #[Option('Password, asked interactively when omitted')] ?string $password = null,
    ): int {
        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            $validate = static function (?string $value): string {
                if (null === $value || mb_strlen($value) < 8) {
                    throw new \RuntimeException('The password must be at least 8 characters long.');
                }

                return $value;
            };
            $password = null !== $password ? $validate($password) : $io->askHidden('Password (min. 8 characters)', $validate);

            $user = new User($name, $email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $this->em->persist($user);
        }

        $user->setRoles([User::ROLE_ADMIN])->setIsActive(true)->markEmailAsVerified();
        $this->em->flush();

        $io->success(\sprintf('Admin "%s" is ready.', $user->getEmail()));

        return Command::SUCCESS;
    }
}

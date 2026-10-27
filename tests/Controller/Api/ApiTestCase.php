<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * HTTP client plus helpers to log in through the real /api/auth/login endpoint and read the JSON envelope.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'Secret1234!';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @return string the bearer token
     */
    protected function loginAsAdmin(): string
    {
        return $this->login($this->createVerifiedUser([User::ROLE_ADMIN]));
    }

    /**
     * @param list<string> $roles
     */
    protected function createVerifiedUser(array $roles): User
    {
        $container = static::getContainer();
        $user = (new User('Test', $this->uniqueEmail()))->setRoles($roles)->markEmailAsVerified();
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $em = $container->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function login(User $user): string
    {
        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();

        return $this->responseData()['token'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
    }

    protected function uniqueEmail(): string
    {
        return uniqid('user_', true).'@example.com';
    }
}

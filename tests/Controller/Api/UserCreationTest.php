<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;

/**
 * Account creation (self-registration and admin), e-mail confirmation and welcome e-mail.
 */
final class UserCreationTest extends ApiTestCase
{
    public function testRegisterCreatesUserWithRoleUserAndSendsConfirmationOnly(): void
    {
        $email = $this->uniqueEmail();

        $this->client->jsonRequest('POST', '/api/auth/register', ['name' => 'Alice', 'email' => $email, 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        self::assertSame(['ROLE_USER'], $data['roles']);
        self::assertNull($data['emailVerifiedAt']);

        self::assertQueuedEmailCount(1);
        self::assertEmailHeaderSame($this->sentEmail(0), 'Subject', 'Confirmez votre adresse e-mail');
    }

    public function testUnconfirmedUserCannotLogIn(): void
    {
        $email = $this->uniqueEmail();
        $this->client->jsonRequest('POST', '/api/auth/register', ['name' => 'Alice', 'email' => $email, 'password' => self::PASSWORD]);

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testConfirmationLinkVerifiesEmailAndSendsWelcomeOnce(): void
    {
        $email = $this->uniqueEmail();
        $this->client->jsonRequest('POST', '/api/auth/register', ['name' => 'Alice', 'email' => $email, 'password' => self::PASSWORD]);
        $link = $this->confirmationLink($this->sentEmail(0));

        $this->client->request('GET', $link);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->responseData()['emailVerifiedAt']);
        self::assertQueuedEmailCount(1);
        $welcome = $this->sentEmail(0);
        self::assertEmailHeaderSame($welcome, 'Subject', 'Bienvenue sur Symfony API');
        self::assertEmailAddressContains($welcome, 'To', $email);

        // Replaying the link keeps the account confirmed but does not send a second welcome e-mail.
        $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertQueuedEmailCount(0);

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testTamperedConfirmationLinkIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', ['name' => 'Alice', 'email' => $this->uniqueEmail(), 'password' => self::PASSWORD]);
        $link = $this->confirmationLink($this->sentEmail(0));

        $this->client->request('GET', preg_replace('/_hash=[^&]+/', '_hash=forged', $link));

        self::assertResponseStatusCodeSame(400);
        self::assertQueuedEmailCount(0);
    }

    public function testAdminCreatesUserWithRoleUserByDefaultAndConfirmationEmail(): void
    {
        $token = $this->loginAsAdmin();
        $email = $this->uniqueEmail();

        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => $email, 'password' => self::PASSWORD], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        self::assertSame(['ROLE_USER'], $data['roles']);
        self::assertNull($data['emailVerifiedAt']);

        self::assertQueuedEmailCount(1);
        $confirmation = $this->sentEmail(0);
        self::assertEmailHeaderSame($confirmation, 'Subject', 'Confirmez votre adresse e-mail');
        self::assertEmailAddressContains($confirmation, 'To', $email);

        $this->client->request('GET', $this->confirmationLink($confirmation));
        self::assertResponseIsSuccessful();
        self::assertEmailHeaderSame($this->sentEmail(0), 'Subject', 'Bienvenue sur Symfony API');
    }

    public function testAdminCanGiveOtherRoles(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Carol', 'email' => $this->uniqueEmail(), 'password' => self::PASSWORD, 'roles' => ['ROLE_ADMIN']], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->responseData()['roles']);
    }

    public function testAdminCannotCreateUserWithExistingEmail(): void
    {
        $token = $this->loginAsAdmin();
        $email = $this->uniqueEmail();
        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => $email, 'password' => self::PASSWORD], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => $email, 'password' => self::PASSWORD], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(409);
        self::assertQueuedEmailCount(0);
    }

    public function testAdminCreationRejectsInvalidRole(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => $this->uniqueEmail(), 'password' => self::PASSWORD, 'roles' => ['ROLE_SUPERHERO']], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegularUserCannotCreateUsers(): void
    {
        $token = $this->login($this->createVerifiedUser([User::ROLE_USER]));
        $email = $this->uniqueEmail();

        $this->client->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => $email, 'password' => self::PASSWORD], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneByEmail($email));
    }

    private function sentEmail(int $index): Email
    {
        $email = self::getMailerMessage($index);
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function confirmationLink(Email $email): string
    {
        self::assertMatchesRegularExpression('#href="([^"]+/api/auth/verify-email[^"]+)"#', $email->getHtmlBody());
        preg_match('#href="([^"]+/api/auth/verify-email[^"]+)"#', $email->getHtmlBody(), $matches);

        return html_entity_decode($matches[1]);
    }
}

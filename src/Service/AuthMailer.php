<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AuthMailer
{
    public const string VERIFICATION_TTL = '+24 hours';

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $from,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function sendEmailVerification(User $user): void
    {
        $url = $this->urlGenerator->generate('api_auth_verify_email', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $signedUrl = $this->uriSigner->sign($url, new \DateTimeImmutable(self::VERIFICATION_TTL));

        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($this->from, 'Symfony API'))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Confirmez votre adresse e-mail')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context(['name' => $user->getName(), 'url' => $signedUrl]));
    }

    public function sendWelcome(User $user): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($this->from, 'Symfony API'))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Bienvenue sur Symfony API')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context(['name' => $user->getName(), 'url' => rtrim($this->frontendUrl, '/')]));
    }

    public function sendPasswordReset(User $user, string $plainToken): void
    {
        $url = rtrim($this->frontendUrl, '/').'/reset-password?token='.$plainToken;

        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($this->from, 'Symfony API'))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context(['name' => $user->getName(), 'url' => $url, 'token' => $plainToken]));
    }
}

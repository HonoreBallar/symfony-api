<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One-time token sent by e-mail to reset a forgotten password. Only the SHA-256 hash is stored.
 */
#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
class PasswordResetToken
{
    public const string TTL = '+1 hour';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    private function __construct(User $user, string $plainToken)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = self::hash($plainToken);
        $this->expiresAt = new \DateTimeImmutable(self::TTL);
    }

    /**
     * @return array{PasswordResetToken, string} the entity and the plain token to e-mail
     */
    public static function issue(User $user): array
    {
        $plainToken = bin2hex(random_bytes(32));

        return [new self($user, $plainToken), $plainToken];
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }
}

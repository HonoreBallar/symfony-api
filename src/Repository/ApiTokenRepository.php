<?php

namespace App\Repository;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findOneByPlainToken(string $plainToken): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => ApiToken::hash($plainToken)]);
    }

    public function deleteByPlainToken(string $plainToken): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', ApiToken::hash($plainToken))
            ->getQuery()
            ->execute();
    }

    public function deleteAllForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}

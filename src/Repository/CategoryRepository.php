<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return Paginator<Category>
     */
    public function paginate(int $page, int $pageSize): Paginator
    {
        $query = $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery();

        return new Paginator($query, fetchJoinCollection: false);
    }

    public function nameExists(string $name, ?Category $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('LOWER(c.name) = LOWER(:name)')
            ->setParameter('name', $name);

        if (null !== $exclude) {
            $qb->andWhere('c.id != :id')->setParameter('id', $exclude->getId(), 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}

<?php

namespace App\Repository;

use App\Entity\Set;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Set>
 */
class SetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Set::class);
    }

    /**
     * @return Set[] Returns an array of available (non-reserved) sets
     */
    public function findAvailableSets(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.reservations', 'r')
            ->where('r.id IS NULL')
            ->andWhere('s.owned = false')
            ->orderBy('s.numeroSet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Set[] Returns wanted sets (not owned, regardless of reservation status)
     */
    public function findWantedSets(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.owned = false')
            ->orderBy('s.numeroSet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Set[] Returns all sets ordered by numero_set
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.numeroSet', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

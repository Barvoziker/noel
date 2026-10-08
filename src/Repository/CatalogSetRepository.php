<?php

namespace App\Repository;

use App\Entity\CatalogSet;
use App\Service\SetNumber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogSet>
 */
class CatalogSetRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 48;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogSet::class);
    }

    public function findOneByNumero(?string $numero): ?CatalogSet
    {
        $numero = SetNumber::normalize($numero);
        if (!$numero) {
            return null;
        }

        return $this->findOneBy(['setNum' => SetNumber::toRebrickable($numero)])
            ?? $this->findOneBy(['numero' => $numero]);
    }

    public function isEmpty(): bool
    {
        return $this->createQueryBuilder('c')->select('1')->setMaxResults(1)->getQuery()->getOneOrNullResult() === null;
    }

    /**
     * @param string[] $excludeNumeros numéros déjà dans la collection, à masquer
     *
     * @return Paginator<CatalogSet>
     */
    public function browse(
        bool $vehiclesOnly = true,
        ?string $query = null,
        ?string $rootTheme = null,
        ?int $fromYear = null,
        ?int $toYear = null,
        array $excludeNumeros = [],
        string $sort = 'recent',
        int $page = 1,
        int $minParts = 1,
    ): Paginator {
        // Les « sets » sans pièces sont des lots ou des produits dérivés
        $qb = $this->createQueryBuilder('c')
            ->where('c.parts >= :minParts')
            ->setParameter('minParts', max(1, $minParts));

        if ($vehiclesOnly) {
            $qb->andWhere('c.vehicle = true');
        }

        TextSearch::apply($qb, $query, ['c.name', 'c.themePath'], 'c.numero');
        if ($rootTheme) {
            $qb->andWhere('c.rootTheme = :root')->setParameter('root', $rootTheme);
        }
        if ($fromYear) {
            $qb->andWhere('c.year >= :from')->setParameter('from', $fromYear);
        }
        if ($toYear) {
            $qb->andWhere('c.year <= :to')->setParameter('to', $toYear);
        }
        if ($excludeNumeros) {
            $qb->andWhere('c.numero NOT IN (:exclude)')->setParameter('exclude', $excludeNumeros);
        }

        match ($sort) {
            'old' => $qb->orderBy('c.year', 'ASC')->addOrderBy('c.setNum', 'ASC'),
            'parts' => $qb->orderBy('c.parts', 'DESC'),
            'name' => $qb->orderBy('c.name', 'ASC'),
            default => $qb->orderBy('c.year', 'DESC')->addOrderBy('c.setNum', 'DESC'),
        };

        $qb->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);

        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Thèmes racines classés par nombre de sets.
     *
     * @return array<string, int>
     */
    public function rootThemes(bool $vehiclesOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.rootTheme AS theme, COUNT(c.setNum) AS total')
            ->where('c.rootTheme IS NOT NULL AND c.parts > 0')
            ->groupBy('c.rootTheme')
            ->orderBy('total', 'DESC');
        if ($vehiclesOnly) {
            $qb->andWhere('c.vehicle = true');
        }

        return array_column($qb->getQuery()->getArrayResult(), 'total', 'theme');
    }

    /**
     * @return array{total: int, vehicles: int, latestYear: ?int}
     */
    public function summary(): array
    {
        $row = $this->createQueryBuilder('c')
            ->select('COUNT(c.setNum) AS total, SUM(CASE WHEN c.vehicle = true THEN 1 ELSE 0 END) AS vehicles, MAX(c.year) AS latestYear')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $row['total'],
            'vehicles' => (int) $row['vehicles'],
            'latestYear' => $row['latestYear'] !== null ? (int) $row['latestYear'] : null,
        ];
    }
}

<?php

namespace App\Repository;

use App\Entity\Set;
use App\Service\SetNumber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Set>
 */
class SetRepository extends ServiceEntityRepository
{
    public const FILTERS = ['available', 'wanted', 'owned', 'all'];
    public const SORTS = ['priorite', 'numero', 'nom', 'prix', 'annee', 'recent'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Set::class);
    }

    public function findOneByNumero(?string $numero): ?Set
    {
        $numero = SetNumber::normalize($numero);

        return $numero ? $this->findOneBy(['numeroSet' => $numero]) : null;
    }

    /**
     * Recherche pour les pages publiques et admin.
     *
     * @param string $filter          available | wanted | owned | all
     * @param bool   $forOwner        true côté admin : cache les sets ajoutés en secret par les proches
     * @param bool   $showReservation false : le filtre « available » ne doit pas tenir compte des réservations
     *
     * @return Set[]
     */
    public function search(
        string $filter = 'all',
        ?string $query = null,
        ?string $theme = null,
        string $sort = 'numero',
        bool $forOwner = false,
        bool $showReservation = true,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.reservations', 'r')
            ->addSelect('r');

        if ($forOwner) {
            $this->excludeGiverSecrets($qb);
        }

        switch ($filter) {
            case 'available':
                $qb->andWhere('s.owned = false');
                if ($showReservation) {
                    $qb->andWhere('r.id IS NULL');
                }
                break;
            case 'wanted':
                $qb->andWhere('s.owned = false');
                break;
            case 'owned':
                $qb->andWhere('s.owned = true');
                break;
        }

        $query = $query !== null ? trim($query) : '';
        if ($query !== '') {
            $qb->andWhere('LOWER(s.nom) LIKE :q OR LOWER(s.numeroSet) LIKE :q OR LOWER(s.theme) LIKE :q OR s.numeroSet = :exact')
                ->setParameter('q', '%'.mb_strtolower(addcslashes($query, '%_')).'%')
                ->setParameter('exact', SetNumber::normalize($query) ?? '');
        }

        if ($theme) {
            $qb->andWhere('s.theme = :theme')->setParameter('theme', $theme);
        }

        match ($sort) {
            'priorite' => $qb->orderBy('s.owned', 'ASC')->addOrderBy('s.priorite', 'ASC')->addOrderBy('s.nom', 'ASC'),
            'nom' => $qb->orderBy('s.nom', 'ASC'),
            'prix' => $qb->orderBy('s.prix', 'ASC')->addOrderBy('s.nom', 'ASC'),
            'annee' => $qb->orderBy('s.annee', 'DESC')->addOrderBy('s.nom', 'ASC'),
            'recent' => $qb->orderBy('s.createdAt', 'DESC')->addOrderBy('s.id', 'DESC'),
            default => $qb->orderBy('s.numeroSet', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function findThemes(bool $forOwner = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT s.theme')
            ->where('s.theme IS NOT NULL')
            ->orderBy('s.theme', 'ASC');

        if ($forOwner) {
            $qb->leftJoin('s.reservations', 'r');
            $this->excludeGiverSecrets($qb);
        }

        return array_column($qb->getQuery()->getScalarResult(), 'theme');
    }

    /**
     * Chiffres de la collection, sans aucune info de réservation.
     *
     * @return array{owned: int, wanted: int, pieces: int, wantedValue: float, ownedValue: float, themes: array<string, int>}
     */
    public function getOwnerStats(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.owned AS owned, COUNT(s.id) AS total, COALESCE(SUM(s.pieces), 0) AS pieces, COALESCE(SUM(s.prix), 0) AS value')
            ->where('s.addedByGiver = false OR s.owned = true')
            ->groupBy('s.owned')
            ->getQuery()
            ->getArrayResult();

        $stats = ['owned' => 0, 'wanted' => 0, 'pieces' => 0, 'wantedValue' => 0.0, 'ownedValue' => 0.0, 'themes' => []];
        foreach ($rows as $row) {
            if ($row['owned']) {
                $stats['owned'] = (int) $row['total'];
                $stats['pieces'] = (int) $row['pieces'];
                $stats['ownedValue'] = (float) $row['value'];
            } else {
                $stats['wanted'] = (int) $row['total'];
                $stats['wantedValue'] = (float) $row['value'];
            }
        }

        $themes = $this->createQueryBuilder('s')
            ->select('s.theme AS theme, COUNT(s.id) AS total')
            ->where('s.owned = true AND s.theme IS NOT NULL')
            ->groupBy('s.theme')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();

        foreach ($themes as $row) {
            $stats['themes'][$row['theme']] = (int) $row['total'];
        }

        return $stats;
    }

    /**
     * Un set ajouté par un proche reste invisible pour l'admin tant qu'il n'est pas possédé.
     */
    private function excludeGiverSecrets(QueryBuilder $qb): void
    {
        $qb->andWhere('s.addedByGiver = false OR s.owned = true');
    }
}

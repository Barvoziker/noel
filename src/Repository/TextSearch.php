<?php

namespace App\Repository;

use App\Service\SearchTerms;
use App\Service\SetNumber;
use Doctrine\ORM\QueryBuilder;

/**
 * Recherche plein texte simple : chaque mot (ou son équivalent anglais) doit apparaître
 * dans l'un des champs. « casque norris » trouve « McLaren … Lando Norris Helmet ».
 */
final class TextSearch
{
    /**
     * @param string[] $fields champs DQL comparés en LIKE, ex : ['s.nom', 's.theme']
     */
    public static function apply(QueryBuilder $qb, ?string $query, array $fields, string $numeroField): void
    {
        $query = trim((string) $query);
        if ($query === '') {
            return;
        }

        if (SetNumber::looksLikeNumber($query)) {
            $qb->andWhere($numeroField.' = :exact OR '.$numeroField.' LIKE :numeroLike')
                ->setParameter('exact', SetNumber::normalize($query))
                ->setParameter('numeroLike', addcslashes((string) SetNumber::normalize($query), '%_').'%');

            return;
        }

        foreach (SearchTerms::groups($query) as $i => $variants) {
            $or = $qb->expr()->orX();
            foreach ($variants as $j => $variant) {
                $param = 'term_'.$i.'_'.$j;
                foreach ([...$fields, $numeroField] as $field) {
                    $or->add('LOWER('.$field.') LIKE :'.$param);
                }
                $qb->setParameter($param, '%'.addcslashes($variant, '%_').'%');
            }
            $qb->andWhere($or);
        }
    }
}

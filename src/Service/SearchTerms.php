<?php

namespace App\Service;

/**
 * Découpe une recherche en mots et ajoute les équivalents anglais des mots français :
 * le catalogue Rebrickable est en anglais (« casque » → « helmet »).
 */
final class SearchTerms
{
    private const SYNONYMS = [
        'casque' => ['helmet'],
        'casques' => ['helmet'],
        'voiture' => ['car'],
        'voitures' => ['car'],
        'course' => ['race', 'racing'],
        'camion' => ['truck'],
        'camions' => ['truck'],
        'moto' => ['motorcycle', 'motorbike', 'bike'],
        'motos' => ['motorcycle', 'motorbike', 'bike'],
        'tracteur' => ['tractor'],
        'grue' => ['crane'],
        'pelleteuse' => ['excavator'],
        'pelle' => ['excavator'],
        'chargeuse' => ['loader'],
        'dépanneuse' => ['tow truck'],
        'depanneuse' => ['tow truck'],
        'pompier' => ['fire'],
        'pompiers' => ['fire'],
        'police' => ['police'],
        'bus' => ['bus'],
        'camionnette' => ['van'],
        'fourgon' => ['van'],
        'quad' => ['quad', 'atv'],
        'chantier' => ['construction'],
        'benne' => ['dump', 'tipper'],
        'bétonnière' => ['cement mixer', 'concrete mixer'],
        'betonniere' => ['cement mixer', 'concrete mixer'],
        'chariot' => ['forklift'],
        'formule' => ['formula'],
        'pilote' => ['driver'],
        'écurie' => ['team'],
        'ecurie' => ['team'],
        'coffret' => ['set', 'pack', 'collection'],
    ];

    /** Mots ignorés : « le casque de Norris » = « casque norris » */
    private const STOP_WORDS = ['le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'the', 'of', 'and', 'a'];

    /**
     * @return list<list<string>> une liste de mots ; pour chacun, les variantes acceptées (en minuscules)
     */
    public static function groups(?string $query): array
    {
        $words = preg_split('/[\s,;]+/u', mb_strtolower(trim((string) $query)), -1, PREG_SPLIT_NO_EMPTY);
        $groups = [];
        foreach ($words as $word) {
            $word = trim($word, "'\"«»().");
            // « l'auto », « d'Ayrton » : on garde le mot après l'apostrophe
            $word = preg_replace("/^[ldj]['’]/u", '', $word);
            if ($word === '' || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $groups[] = array_values(array_unique([$word, ...(self::SYNONYMS[$word] ?? [])]));
        }

        return $groups;
    }
}

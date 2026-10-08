<?php

namespace App\Service;

/**
 * Normalisation des numéros de set LEGO.
 *
 * Les gens tapent « 42143 », « #42143 », « Set 42143 », « 42143-1 » (format Rebrickable/Brickset)
 * ou « 42143 » avec des espaces : tout ça doit désigner le même set.
 */
final class SetNumber
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = mb_strtolower(trim($raw));
        $value = preg_replace('/^(set|n°|no\.?|#)\s*(?=\d)/u', '', $value);
        $value = preg_replace('/\s+/u', '', $value);
        // Le suffixe « -1 » est la première version du set chez Rebrickable/Brickset
        $value = preg_replace('/-1$/', '', $value);

        return $value === '' ? null : $value;
    }

    /**
     * Vrai si la saisie ressemble à un numéro de set plutôt qu'à un nom.
     */
    public static function looksLikeNumber(?string $raw): bool
    {
        $value = self::normalize($raw);

        return $value !== null && (bool) preg_match('/^\d{3,7}(-\d+)?$/', $value);
    }

    public static function toRebrickable(string $numero): string
    {
        return str_contains($numero, '-') ? $numero : $numero.'-1';
    }

    public static function rebrickableImage(string $numero): string
    {
        return 'https://cdn.rebrickable.com/media/sets/'.rawurlencode(self::toRebrickable($numero)).'.jpg';
    }
}

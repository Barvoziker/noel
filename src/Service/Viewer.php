<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Qui regarde la page publique ? Sert à protéger la surprise :
 * le propriétaire de la collection ne doit jamais voir les réservations.
 *
 * - Cookie « owner » : posé automatiquement dès que l'admin se connecte. Il gagne toujours.
 * - Cookie « giver » : posé quand un proche clique « Je veux offrir un cadeau ».
 */
class Viewer
{
    public const OWNER_COOKIE = 'cl_owner';
    public const GIVER_COOKIE = 'cl_giver';
    public const MINE_COOKIE = 'cl_mine';

    private const ONE_YEAR = 31536000;

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function isOwner(): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()?->cookies->get(self::OWNER_COOKIE);
    }

    public function isGiver(): bool
    {
        return !$this->isOwner() && (bool) $this->requestStack->getCurrentRequest()?->cookies->get(self::GIVER_COOKIE);
    }

    /**
     * Les réservations ne sont révélées qu'aux proches identifiés.
     */
    public function canSeeReservations(): bool
    {
        return $this->isGiver();
    }

    public function hasChosen(): bool
    {
        return $this->isOwner() || $this->isGiver();
    }

    /**
     * Réservations faites depuis ce navigateur : [anonymousId => code d'annulation].
     *
     * @return array<string, string>
     */
    public function getMyReservations(): array
    {
        $raw = $this->requestStack->getCurrentRequest()?->cookies->get(self::MINE_COOKIE);
        if (!$raw) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? array_filter($data, 'is_string') : [];
    }

    /**
     * @param array<string, string> $reservations
     */
    public static function myReservationsCookie(array $reservations): Cookie
    {
        return Cookie::create(self::MINE_COOKIE, json_encode($reservations), time() + self::ONE_YEAR, '/', null, null, true, false, Cookie::SAMESITE_LAX);
    }

    public static function roleCookie(string $name, bool $enabled): Cookie
    {
        return Cookie::create($name, $enabled ? '1' : '', $enabled ? time() + self::ONE_YEAR : 1, '/', null, null, true, false, Cookie::SAMESITE_LAX);
    }
}

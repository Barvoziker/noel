<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ReservationHashService
{
    // Sans 0/O/1/I/L pour éviter les erreurs de recopie
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secretKey,
    ) {
    }

    /**
     * Hash le prénom pour qu'il ne soit lisible par personne, même en base.
     */
    public function hashReservationData(?string $reservedBy): ?string
    {
        $reservedBy = $reservedBy !== null ? trim($reservedBy) : null;
        if (!$reservedBy) {
            return null;
        }

        return hash_hmac('sha256', mb_strtolower($reservedBy), $this->secretKey);
    }

    public function generateAnonymousId(): string
    {
        return 'gift_'.bin2hex(random_bytes(8));
    }

    /**
     * Code court à recopier (ex : K7P-2QX), utilisé pour annuler une réservation.
     */
    public function generateCancelCode(): string
    {
        $code = '';
        $max = strlen(self::CODE_ALPHABET) - 1;
        for ($i = 0; $i < 6; ++$i) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return substr($code, 0, 3).'-'.substr($code, 3);
    }

    public function hashCancelCode(string $code): string
    {
        return hash_hmac('sha256', self::normalizeCode($code), $this->secretKey);
    }

    public function isCancelCodeValid(string $code, ?string $hash): bool
    {
        return $hash !== null && hash_equals($hash, $this->hashCancelCode($code));
    }

    private static function normalizeCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }
}

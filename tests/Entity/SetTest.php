<?php

namespace App\Tests\Entity;

use App\Entity\Reservation;
use App\Entity\Set;
use PHPUnit\Framework\TestCase;

class SetTest extends TestCase
{
    public function testReservationIsNeverRevealedToOwner(): void
    {
        $set = (new Set())->setNumeroSet('42143');
        $set->addReservation(new Reservation());

        self::assertSame('reserved', $set->getStatus(true));
        self::assertSame('available', $set->getStatus(false));
    }

    public function testOwnedWinsOverReservation(): void
    {
        $set = (new Set())->setOwned(true);
        $set->addReservation(new Reservation());

        self::assertSame('owned', $set->getStatus(true));
        self::assertNotNull($set->getOwnedAt());

        $set->setOwned(false);
        self::assertNull($set->getOwnedAt());
    }

    public function testPriceIsNormalized(): void
    {
        self::assertSame('129.99', (new Set())->setPrix(' 129,99 € ')->getPrix());
        self::assertNull((new Set())->setPrix('')->getPrix());
    }

    public function testDisplayImageFallsBackToRebrickable(): void
    {
        $set = (new Set())->setNumeroSet('#75192-1');
        self::assertSame('https://cdn.rebrickable.com/media/sets/75192-1.jpg', $set->getDisplayImage());

        $set->setImageUrl('https://example.com/a.jpg');
        self::assertSame('https://example.com/a.jpg', $set->getDisplayImage());
    }
}

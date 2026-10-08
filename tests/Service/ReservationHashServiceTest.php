<?php

namespace App\Tests\Service;

use App\Service\ReservationHashService;
use PHPUnit\Framework\TestCase;

class ReservationHashServiceTest extends TestCase
{
    public function testCancelCodeRoundTrip(): void
    {
        $service = new ReservationHashService('secret');
        $code = $service->generateCancelCode();

        self::assertMatchesRegularExpression('/^[A-Z2-9]{3}-[A-Z2-9]{3}$/', $code);

        $hash = $service->hashCancelCode($code);
        self::assertTrue($service->isCancelCodeValid($code, $hash));
        // Tolère minuscules, espaces et tiret oublié
        self::assertTrue($service->isCancelCodeValid(' '.strtolower(str_replace('-', '', $code)), $hash));
        self::assertFalse($service->isCancelCodeValid('AAA-AAA', $hash));
        self::assertFalse($service->isCancelCodeValid($code, null));
    }

    public function testNameHashDoesNotLeakName(): void
    {
        $service = new ReservationHashService('secret');

        self::assertNull($service->hashReservationData('  '));
        self::assertStringNotContainsString('Marie', (string) $service->hashReservationData('Marie'));
    }
}

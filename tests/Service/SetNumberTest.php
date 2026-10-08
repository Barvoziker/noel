<?php

namespace App\Tests\Service;

use App\Service\SetNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SetNumberTest extends TestCase
{
    public static function numbers(): iterable
    {
        yield 'simple' => ['42143', '42143'];
        yield 'espaces' => ['  42143 ', '42143'];
        yield 'format Rebrickable' => ['42143-1', '42143'];
        yield 'deuxième version conservée' => ['10255-2', '10255-2'];
        yield 'dièse' => ['#42143', '42143'];
        yield 'préfixe Set' => ['Set 42143', '42143'];
        yield 'n°' => ['n° 42143', '42143'];
        yield 'vide' => ['   ', null];
        yield 'nom intact' => ['Nova', 'nova'];
    }

    #[DataProvider('numbers')]
    public function testNormalize(string $raw, ?string $expected): void
    {
        self::assertSame($expected, SetNumber::normalize($raw));
    }

    public function testLooksLikeNumber(): void
    {
        self::assertTrue(SetNumber::looksLikeNumber('#75192-1'));
        self::assertFalse(SetNumber::looksLikeNumber('Ferrari'));
        self::assertFalse(SetNumber::looksLikeNumber('12'));
    }

    public function testRebrickableImage(): void
    {
        self::assertSame('https://cdn.rebrickable.com/media/sets/42143-1.jpg', SetNumber::rebrickableImage('42143'));
        self::assertSame('https://cdn.rebrickable.com/media/sets/10255-2.jpg', SetNumber::rebrickableImage('10255-2'));
    }
}

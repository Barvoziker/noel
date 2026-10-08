<?php

namespace App\Tests\Service;

use App\Service\SearchTerms;
use PHPUnit\Framework\TestCase;

class SearchTermsTest extends TestCase
{
    public function testFrenchWordsGetEnglishEquivalents(): void
    {
        self::assertSame([['casque', 'helmet'], ['norris']], SearchTerms::groups('le casque de Norris'));
    }

    public function testApostrophesAndPunctuation(): void
    {
        self::assertSame([['casque', 'helmet'], ['ayrton']], SearchTerms::groups("Casque d'Ayrton"));
    }

    public function testEmpty(): void
    {
        self::assertSame([], SearchTerms::groups('  de la '));
    }
}

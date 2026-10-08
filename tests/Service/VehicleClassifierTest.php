<?php

namespace App\Tests\Service;

use App\Service\VehicleClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VehicleClassifierTest extends TestCase
{
    private VehicleClassifier $classifier;

    protected function setUp(): void
    {
        // Extrait de l'arbre des thèmes Rebrickable
        $this->classifier = new VehicleClassifier();
        $this->classifier->setThemes([
            1 => ['name' => 'Technic', 'parent' => null],
            52 => ['name' => 'City', 'parent' => null],
            63 => ['name' => 'Traffic', 'parent' => 52],
            171 => ['name' => 'Star Wars', 'parent' => null],
            501 => ['name' => 'Gear', 'parent' => null],
            504 => ['name' => 'Duplo', 'parent' => null],
            505 => ['name' => 'Disney', 'parent' => 504],
            506 => ['name' => 'Cars', 'parent' => 505],
            507 => ['name' => 'Town', 'parent' => 504],
            601 => ['name' => 'Speed Champions', 'parent' => null],
            695 => ['name' => 'Super Heroes DC', 'parent' => null],
            721 => ['name' => 'Icons', 'parent' => null],
        ]);
    }

    public static function sets(): iterable
    {
        // [nom, thème, pièces, attendu]
        yield 'Speed Champions : tout est voiture' => ['2 Fast 2 Furious Nissan Skyline GT-R (R34)', 601, 319, true];
        yield 'sous-thème véhicule' => ['Garbage Truck', 63, 248, true];
        yield 'Technic voiture' => ['Ferrari Daytona SP3', 1, 3778, true];
        yield 'Technic engin de chantier' => ['Liebherr Crawler Crane LR 13000', 1, 2883, true];
        yield 'Icons marque' => ['Land Rover Classic Defender 90', 721, 2336, true];
        yield 'Icons scooter' => ['Vespa 125', 721, 1106, true];
        yield 'licence' => ['Batmobile Tumbler', 695, 2049, true];
        yield 'garage avec voiture' => ['Custom Garage Ford Mustang GT Car', 1, 1200, true];
        yield 'Technic hélicoptère' => ['Airbus H175 Rescue Helicopter', 1, 2001, false];
        yield 'Technic bateau' => ['Power Boat', 1, 174, false];
        yield 'rover à roues' => ['Lunar Outpost Moon Rover Space Vehicle', 1, 1200, true];
        yield 'Mustang Dark Horse (pas un cheval)' => ['Ford Mustang Dark Horse Sports Car', 601, 347, true];
        yield 'Duplo Cars malgré Duplo exclu' => ['Mack at the Race', 506, 14, true];
        yield 'autre Duplo exclu' => ['Fire Truck', 507, 20, false];
        yield 'coffret sans pièces' => ['NEOM McLaren Racing Gift Set', 1, 0, true];
        yield 'Icons vaisseau' => ['Galaxy Explorer', 721, 1254, false];
        yield 'Icons vélo' => ['Road Bike', 721, 600, false];
        yield 'Star Wars' => ['Millennium Falcon', 171, 7541, false];
        yield 'produit dérivé exclu' => ['Ferrari Key Chain', 501, 12, false];
        yield 'goodies sans pièces' => ['Minifigure Pack', 52, 0, false];
        yield 'chat, pas Caterpillar' => ['Cat', 52, 40, false];
    }

    #[DataProvider('sets')]
    public function testIsVehicle(string $name, int $themeId, int $parts, bool $expected): void
    {
        self::assertSame($expected, $this->classifier->isVehicle($name, $themeId, $parts));
    }

    public function testThemePath(): void
    {
        self::assertSame('City > Traffic', $this->classifier->themePath(63));
    }
}

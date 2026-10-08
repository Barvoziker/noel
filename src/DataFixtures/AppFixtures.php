<?php

namespace App\DataFixtures;

use App\Entity\Set;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // [numéro, nom, thème, année, pièces, possédé, prix, priorité, note]
        $sets = [
            ['75192', 'Millennium Falcon', 'Star Wars', 2017, 7541, false, '849.99', Set::PRIORITY_HIGH, 'Le rêve absolu, à se partager à plusieurs ?'],
            ['42143', 'Ferrari Daytona SP3', 'Technic', 2022, 3778, false, '449.99', Set::PRIORITY_HIGH, null],
            ['10497', 'Galaxy Explorer', 'Icons', 2022, 1254, false, '99.99', Set::PRIORITY_NORMAL, null],
            ['21058', 'Great Pyramid of Giza', 'Architecture', 2022, 1476, false, '139.99', Set::PRIORITY_NORMAL, null],
            ['31132', 'Viking Ship and the Midgard Serpent', 'Creator 3-in-1', 2022, 1192, false, '119.99', Set::PRIORITY_LOW, null],
            ['75302', 'Imperial Shuttle', 'Star Wars', 2021, 660, true, '69.99', Set::PRIORITY_NORMAL, null],
            ['60367', 'Passenger Airplane', 'City', 2023, 913, true, '99.99', Set::PRIORITY_NORMAL, null],
            ['10294', 'Titanic', 'Icons', 2021, 9090, true, '679.99', Set::PRIORITY_NORMAL, null],
        ];

        foreach ($sets as [$numero, $nom, $theme, $annee, $pieces, $owned, $prix, $priorite, $notes]) {
            $manager->persist((new Set())
                ->setNumeroSet($numero)
                ->setNom($nom)
                ->setTheme($theme)
                ->setAnnee($annee)
                ->setPieces($pieces)
                ->setOwned($owned)
                ->setPrix($prix)
                ->setPriorite($priorite)
                ->setNotes($notes));
        }

        $manager->flush();
    }
}

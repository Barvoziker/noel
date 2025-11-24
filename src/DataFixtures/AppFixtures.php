<?php

namespace App\DataFixtures;

use App\Entity\Set;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $sets = [
            [
                'numero_set' => '75302',
                'nom' => 'Imperial Star Destroyer',
                'theme' => 'Star Wars',
                'annee' => 2020,
                'image_url' => 'https://www.lego.com/cdn/cs/set/assets/blt77c0b3d0e4c3e5b5/75302.jpg'
            ],
            [
                'numero_set' => '10497',
                'nom' => 'Galaxy Explorer',
                'theme' => 'Icons',
                'annee' => 2022,
                'image_url' => null
            ],
            [
                'numero_set' => '21058',
                'nom' => 'Great Pyramid of Giza',
                'theme' => 'Architecture',
                'annee' => 2022,
                'image_url' => null
            ],
            [
                'numero_set' => '60367',
                'nom' => 'Passenger Airplane',
                'theme' => 'City',
                'annee' => 2023,
                'image_url' => null
            ],
            [
                'numero_set' => '31132',
                'nom' => 'Viking Ship and the Midgard Serpent',
                'theme' => 'Creator 3-in-1',
                'annee' => 2022,
                'image_url' => null
            ]
        ];

        foreach ($sets as $setData) {
            $set = new Set();
            $set->setNumeroSet($setData['numero_set']);
            $set->setNom($setData['nom']);
            $set->setTheme($setData['theme']);
            $set->setAnnee($setData['annee']);
            $set->setImageUrl($setData['image_url']);
            
            $manager->persist($set);
        }

        $manager->flush();
    }
}

<?php

namespace App\Service;

/**
 * Décide si un set LEGO est une voiture ou un véhicule motorisé terrestre.
 *
 * À partir de l'arbre des thèmes Rebrickable (ids stables) :
 *  - Thèmes 100 % véhicules (Speed Champions, Racers…) : tout est retenu, sauf nom clairement hors sujet.
 *  - Ailleurs (Technic, Icons, City, licences…) : retenu si le nom évoque un véhicule terrestre
 *    (type ou marque, ex : « Ferrari », « Batmobile », « Excavator ») et pas un avion, bateau, vaisseau…
 * Les thèmes « produits dérivés » (livres, porte-clés, magnets…) sont toujours exclus.
 */
final class VehicleClassifier
{
    /** Thèmes dont tous les sets sont des véhicules (sous-thèmes inclus) */
    public const VEHICLE_THEMES = [
        601 => 'Speed Champions',
        112 => 'Racers',
        269 => 'Cars',
        717 => 'Speed Racer',
        63 => 'City > Traffic',
        91 => 'Town > Race',
        368 => 'HO 1:87 Vehicles',
        615 => 'Juniors > Cars',
        17 => 'Technic > Speed Slammers',
        506 => 'Duplo > Disney > Cars',
        774 => 'Collectible Minifigures > Formula 1',
    ];

    /** Thèmes jamais retenus (sous-thèmes inclus) : produits dérivés, pièces détachées… */
    public const EXCLUDED_THEMES = [
        206, // Seasonal (calendriers de l'Avent…)
        254, // Bulk Bricks
        443, // Service Packs
        497, // Books
        500, // Clikits
        501, // Gear (porte-clés, magnets, montres…)
        504, // Duplo
        507, // Educational and Dacta
        535, // Collectible Minifigures
        604, // Dimensions
        690, // Super Mario
        709, // LEGO Art
    ];

    /**
     * Véhicule sans ambiguïté (type ou marque) : l'emporte sur les mots de contexte.
     * Ex : « Custom Garage Ford Mustang » reste un véhicule malgré « garage ».
     */
    private const STRONG_WORDS = [
        'car', 'cars', 'supercar', 'hypercar', 'race car', 'racer', 'roadster', 'coupe', 'cabriolet', 'convertible',
        'truck', 'pickup', 'pick-up', 'lorry', 'van', 'bus', 'camper', 'jeep', 'suv', 'buggy', 'dragster', 'hot rod',
        'motorcycle', 'motorbike', 'motor bike', 'chopper', 'scooter', 'quad', 'quad bike', 'atv', 'trike', 'kart', 'go-kart', 'go-cart',
        'stunt bike', 'dirt bike', 'motocross', 'snowmobile', 'batcycle', 'monster truck', 'tow truck', 'fire engine', 'police car',
        'ambulance', 'taxi', 'limousine', 'interceptor', 'vehicle', 'off-roader', 'offroader', '4x4', 'all-terrain',
        'tractor', 'excavator', 'bulldozer', 'dozer', 'crane', 'loader', 'wheel loader', 'dump truck', 'digger', 'forklift',
        'fork lift', 'grader', 'road roller', 'cement mixer', 'concrete mixer', 'tanker', 'haul truck', 'telehandler',
        'rover', 'roving vehicle', 'harvester', 'cherry picker', 'material handler', 'forest machine', 'snow groomer', 'tipper', 'dumper',
        // marques et véhicules célèbres
        'ferrari', 'porsche', 'lamborghini', 'bugatti', 'mclaren', 'bmw', 'mercedes', 'mercedes-amg', 'mercedes-benz', 'audi',
        'volkswagen', 'vw', 'ford', 'mustang', 'chevrolet', 'corvette', 'camaro', 'dodge', 'land rover', 'aston martin',
        'jaguar', 'koenigsegg', 'pagani', 'lotus', 'alfa romeo', 'fiat', 'mini cooper', 'toyota', 'nissan',
        'honda', 'mazda', 'subaru', 'peugeot', 'renault', 'citroen', 'citroën', 'volvo', 'scania', 'liebherr', 'mack',
        'ducati', 'kawasaki', 'yamaha', 'harley-davidson', 'red bull racing', 'williams racing', 'kick sauber', 'haas f1',
        'batmobile', 'tumbler', 'delorean', 'ecto-1', 'speed racer', 'mach 5', 'formula 1', 'f1', 'formula e', 'nascar',
        'lightning mcqueen', 'monster jam', 'fast & furious', 'rolls-royce', 'bentley', 'maserati', 'shelby', 'vespa',
        'back to the future', 'cybertruck', 'pontiac', 'cadillac', 'hummer', 'unimog', 'claas', 'john deere', 'jcb', 'caterpillar',
    ];

    /** Évoque un véhicule mais reste vague : suffit seulement sans mot de contexte */
    private const WEAK_WORDS = ['racing', 'rally', 'grand prix', 'le mans', 'drift', 'auto', 'roadwork', 'pursuit', 'record breaker'];

    /** Pas un véhicule terrestre motorisé : élimine toujours (avion, bateau, vaisseau, vélo, train…) */
    private const HARD_NEGATIVE_WORDS = [
        'plane', 'airplane', 'aeroplane', 'seaplane', 'jet', 'helicopter', 'gyrocopter', 'airbus', 'boeing', 'aircraft', 'airliner',
        'glider', 'drone', 'osprey', 'vtol', 'jet ski', 'water scooter', 'jetski', 'boat', 'ship', 'submarine', 'yacht', 'ferry', 'hovercraft', 'catamaran', 'sailboat',
        'spaceship', 'starship', 'space shuttle', 'rocket',
        'satellite', 'x-wing', 'tie fighter', 'robot', 'mech', 'exoskeleton', 'droid',
        'road bike', 'bicycle', 'bmx', 'train', 'locomotive', 'tram', 'carousel', 'fairground', 'ferris', 'roller coaster',
        'carriage', 'key chain', 'keychain', 'magnet', 'book', 'poster', 'watch', 'puzzle',
    ];

    /** Le set est surtout autre chose (bâtiment, lot…) sauf si un mot fort dit le contraire */
    private const CONTEXT_WORDS = [
        'house', 'station', 'garage', 'building', 'castle', 'tower', 'airport', 'shop', 'store', 'headquarters',
        'game', 'cart', 'caravan', 'cargo', 'card', 'scarecrow', 'carnival', 'collection', 'bundle', 'kit', 'pack',
    ];

    /** @var array<int, int|null> */
    private array $parents = [];
    /** @var array<int, string> */
    private array $names = [];

    /**
     * @param array<int, array{name: string, parent: int|null}> $themes
     */
    public function setThemes(array $themes): void
    {
        $this->parents = array_map(fn ($t) => $t['parent'], $themes);
        $this->names = array_map(fn ($t) => $t['name'], $themes);
    }

    public function themePath(int $themeId): string
    {
        $parts = [];
        for ($id = $themeId, $guard = 0; $id !== null && isset($this->names[$id]) && $guard < 10; $id = $this->parents[$id] ?? null, ++$guard) {
            array_unshift($parts, $this->names[$id]);
        }

        return implode(' > ', $parts);
    }

    public function isVehicle(string $name, ?int $themeId, ?int $parts = null): bool
    {
        if (self::matches($name, self::HARD_NEGATIVE_WORDS)) {
            return false;
        }

        // Un thème véhicule l'emporte, même sous un thème exclu (ex : Duplo > Disney > Cars)
        $ancestors = $themeId !== null ? $this->ancestors($themeId) : [];
        if (array_intersect($ancestors, array_keys(self::VEHICLE_THEMES))) {
            return true;
        }
        if (array_intersect($ancestors, self::EXCLUDED_THEMES)) {
            return false;
        }
        // Les coffrets et lots (0 pièce chez Rebrickable) comptent s'ils citent un véhicule
        if (self::matches($name, self::STRONG_WORDS)) {
            return true;
        }
        if ($parts !== null && $parts < 10) {
            return false; // goodies, minifigs seules
        }

        return self::matches($name, self::WEAK_WORDS) && !self::matches($name, self::CONTEXT_WORDS);
    }

    /**
     * @return int[] le thème et tous ses parents
     */
    private function ancestors(int $themeId): array
    {
        $ids = [];
        for ($id = $themeId, $guard = 0; $id !== null && $guard < 10; $id = $this->parents[$id] ?? null, ++$guard) {
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param string[] $words
     */
    private static function matches(string $name, array $words): bool
    {
        $haystack = ' '.mb_strtolower($name).' ';
        foreach ($words as $word) {
            if (preg_match('/(?<![\p{L}\d])'.preg_quote($word, '/').'(?![\p{L}\d])/u', $haystack)) {
                return true;
            }
        }

        return false;
    }
}

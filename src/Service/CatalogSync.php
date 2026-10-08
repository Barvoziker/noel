<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Télécharge le catalogue complet de Rebrickable (fichiers CSV publics, mis à jour chaque jour,
 * sans clé API ni limite d'appels) et le recopie dans la table catalog_sets.
 *
 * @see https://rebrickable.com/downloads/
 */
class CatalogSync
{
    private const THEMES_URL = 'https://cdn.rebrickable.com/media/downloads/themes.csv.gz';
    private const SETS_URL = 'https://cdn.rebrickable.com/media/downloads/sets.csv.gz';
    private const BATCH = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly VehicleClassifier $classifier,
    ) {
    }

    /**
     * @return array{total: int, vehicles: int}
     */
    public function sync(): array
    {
        $themes = [];
        foreach ($this->downloadCsv(self::THEMES_URL) as [$id, $name, $parent]) {
            $themes[(int) $id] = ['name' => $name, 'parent' => $parent === '' ? null : (int) $parent];
        }
        $this->classifier->setThemes($themes);

        $rows = [];
        $vehicles = 0;
        foreach ($this->downloadCsv(self::SETS_URL) as $cols) {
            if (count($cols) < 6) {
                continue;
            }
            [$setNum, $name, $year, $themeId, $parts, $img] = $cols;
            $themeId = $themeId === '' ? null : (int) $themeId;
            $path = $themeId !== null ? $this->classifier->themePath($themeId) : null;
            $vehicle = $this->classifier->isVehicle($name, $themeId, (int) $parts);
            $vehicles += (int) $vehicle;

            $rows[] = [
                mb_substr($setNum, 0, 30),
                mb_substr(SetNumber::normalize($setNum) ?? $setNum, 0, 30),
                mb_substr($name, 0, 255),
                $year === '' ? null : (int) $year,
                $themeId,
                $path ? mb_substr($path, 0, 255) : null,
                $path ? mb_substr(explode(' > ', $path)[0], 0, 100) : null,
                $parts === '' ? null : (int) $parts,
                $img !== '' ? mb_substr($img, 0, 255) : null,
                $vehicle,
            ];
        }

        if (count($rows) < 1000) {
            throw new \RuntimeException(sprintf('Catalogue Rebrickable suspect (%d sets) : synchronisation annulée.', count($rows)));
        }

        $this->connection->transactional(function (Connection $conn) use ($rows) {
            $conn->executeStatement('DELETE FROM catalog_sets');
            foreach (array_chunk($rows, self::BATCH) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
                $params = array_merge(...array_map(
                    fn (array $r) => [...array_slice($r, 0, 9), $r[9] ? 'true' : 'false'],
                    $chunk,
                ));
                $conn->executeStatement(
                    'INSERT INTO catalog_sets (set_num, numero, name, year, theme_id, theme_path, root_theme, parts, img_url, vehicle) VALUES '.$placeholders,
                    $params,
                );
            }
        });

        return ['total' => count($rows), 'vehicles' => $vehicles];
    }

    /**
     * @return \Generator<string[]>
     */
    private function downloadCsv(string $url): \Generator
    {
        $gz = $this->httpClient->request('GET', $url, ['timeout' => 60])->getContent();
        $csv = gzdecode($gz);
        if ($csv === false) {
            throw new \RuntimeException('Fichier Rebrickable illisible : '.$url);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        fgetcsv($handle, null, ',', '"', ''); // en-tête
        while (($cols = fgetcsv($handle, null, ',', '"', '')) !== false) {
            yield $cols;
        }
        fclose($handle);
    }
}

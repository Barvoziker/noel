<?php

namespace App\Service;

use App\Repository\CatalogSetRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Infos d'un set (nom, thème, année, pièces, image, véhicule ?).
 *
 * 1. Catalogue local (table catalog_sets, voir app:catalog:sync) : instantané, sans clé.
 * 2. Sinon, API Rebrickable si REBRICKABLE_API_KEY est définie : utile pour un set sorti
 *    depuis la dernière synchronisation. L'API est limitée à ~1 requête/s, d'où le cache.
 */
class LegoCatalog
{
    private const API = 'https://rebrickable.com/api/v3/lego/';

    public function __construct(
        private readonly CatalogSetRepository $catalogRepository,
        private readonly VehicleClassifier $classifier,
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::REBRICKABLE_API_KEY)%')]
        private readonly ?string $apiKey = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->apiKey || !$this->catalogRepository->isEmpty();
    }

    /**
     * @return array{numero: string, nom: string, theme: ?string, themePath: ?string, annee: ?int, pieces: ?int, imageUrl: ?string, vehicle: bool}|null
     */
    public function find(string $numero): ?array
    {
        $numero = SetNumber::normalize($numero);
        if (!$numero) {
            return null;
        }

        if ($local = $this->catalogRepository->findOneByNumero($numero)) {
            return [
                'numero' => $numero,
                'nom' => $local->getName(),
                'theme' => $local->getTheme(),
                'themePath' => $local->getThemePath(),
                'annee' => $local->getYear(),
                'pieces' => $local->getParts(),
                'imageUrl' => $local->getImgUrl(),
                'vehicle' => $local->isVehicle(),
            ];
        }

        return $this->apiKey ? $this->findOnApi($numero) : null;
    }

    private function findOnApi(string $numero): ?array
    {
        return $this->cache->get('lego_set_'.md5($numero), function (ItemInterface $item) use ($numero) {
            $set = $this->request('sets/'.rawurlencode(SetNumber::toRebrickable($numero)).'/');
            // Introuvable : on retient l'échec moins longtemps
            $item->expiresAfter($set ? 2592000 : 3600);

            if (!$set) {
                return null;
            }

            $themeId = isset($set['theme_id']) ? (int) $set['theme_id'] : null;
            $theme = $themeId ? $this->request('themes/'.$themeId.'/') : null;
            if ($theme) {
                // Suffisant pour le classement : le thème et son parent direct
                $this->classifier->setThemes(array_filter([
                    $themeId => ['name' => $theme['name'], 'parent' => $theme['parent_id'] ?? null],
                ]));
            }

            return [
                'numero' => $numero,
                'nom' => $set['name'] ?? $numero,
                'theme' => $theme['name'] ?? null,
                'themePath' => $theme['name'] ?? null,
                'annee' => isset($set['year']) ? (int) $set['year'] : null,
                'pieces' => isset($set['num_parts']) ? (int) $set['num_parts'] : null,
                'imageUrl' => $set['set_img_url'] ?? null,
                'vehicle' => $this->classifier->isVehicle($set['name'] ?? '', $themeId, $set['num_parts'] ?? null),
            ];
        });
    }

    private function request(string $path): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::API.$path, [
                'headers' => ['Authorization' => 'key '.$this->apiKey, 'Accept' => 'application/json'],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->warning('Rebrickable indisponible : {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }
}

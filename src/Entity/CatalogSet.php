<?php

namespace App\Entity;

use App\Repository\CatalogSetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Copie locale du catalogue Rebrickable (tous les sets LEGO existants).
 * Remplie par la commande app:catalog:sync, jamais modifiée à la main.
 */
#[ORM\Entity(repositoryClass: CatalogSetRepository::class, readOnly: true)]
#[ORM\Table(name: 'catalog_sets')]
#[ORM\Index(name: 'idx_catalog_numero', columns: ['numero'])]
#[ORM\Index(name: 'idx_catalog_vehicle_year', columns: ['vehicle', 'year'])]
class CatalogSet
{
    /** Identifiant Rebrickable, ex : « 42143-1 » */
    #[ORM\Id]
    #[ORM\Column(length: 30)]
    private string $setNum;

    /** Numéro normalisé comme dans App\Entity\Set, ex : « 42143 » */
    #[ORM\Column(length: 30)]
    private string $numero;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(nullable: true)]
    private ?int $themeId = null;

    /** Chemin complet du thème, ex : « City > Traffic » */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $themePath = null;

    /** Thème racine, ex : « City » (sert aux filtres) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $rootTheme = null;

    #[ORM\Column(nullable: true)]
    private ?int $parts = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imgUrl = null;

    #[ORM\Column(type: 'boolean')]
    private bool $vehicle = false;

    public function getSetNum(): string
    {
        return $this->setNum;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getThemeId(): ?int
    {
        return $this->themeId;
    }

    public function getThemePath(): ?string
    {
        return $this->themePath;
    }

    public function getRootTheme(): ?string
    {
        return $this->rootTheme;
    }

    /** Thème le plus précis, ex : « Traffic » pour « City > Traffic » */
    public function getTheme(): ?string
    {
        if (!$this->themePath) {
            return null;
        }
        $parts = explode(' > ', $this->themePath);

        return end($parts);
    }

    public function getParts(): ?int
    {
        return $this->parts;
    }

    public function getImgUrl(): ?string
    {
        return $this->imgUrl;
    }

    public function isVehicle(): bool
    {
        return $this->vehicle;
    }
}

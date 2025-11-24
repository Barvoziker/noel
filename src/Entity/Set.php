<?php

namespace App\Entity;

use App\Repository\SetRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SetRepository::class)]
#[ORM\Table(name: 'sets')]
class Set
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $numeroSet = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $theme = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1950, max: 2030)]
    private ?int $annee = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $owned = false;

    #[ORM\OneToMany(mappedBy: 'set', targetEntity: Reservation::class, cascade: ['remove'])]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroSet(): ?string
    {
        return $this->numeroSet;
    }

    public function setNumeroSet(string $numeroSet): static
    {
        $this->numeroSet = $numeroSet;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(?int $annee): static
    {
        $this->annee = $annee;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function isReserved(): bool
    {
        return !$this->reservations->isEmpty();
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservations->first() ?: null;
    }

    public function isOwned(): bool
    {
        return $this->owned;
    }

    public function setOwned(bool $owned): static
    {
        $this->owned = $owned;
        return $this;
    }

    public function getStatusLabel(): string
    {
        if ($this->owned) {
            return 'Possédé';
        }
        if ($this->isReserved()) {
            return 'Réservé';
        }
        return 'Disponible';
    }

    public function getStatusBadgeClass(): string
    {
        if ($this->owned) {
            return 'badge-info';
        }
        if ($this->isReserved()) {
            return 'badge-danger';
        }
        return 'badge-success';
    }
}

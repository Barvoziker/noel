<?php

namespace App\Entity;

use App\Repository\SetRepository;
use App\Service\SetNumber;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SetRepository::class)]
#[ORM\Table(name: 'sets')]
class Set
{
    public const PRIORITY_HIGH = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_LOW = 3;

    public const PRIORITY_LABELS = [
        self::PRIORITY_HIGH => 'Très envie',
        self::PRIORITY_NORMAL => 'Envie',
        self::PRIORITY_LOW => 'Si l\'occasion se présente',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro du set est obligatoire.')]
    #[Assert\Length(max: 20)]
    private ?string $numeroSet = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du set est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $theme = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1949, max: 2100)]
    private ?int $annee = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 500)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $owned = false;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $pieces = null;

    /** Prix indicatif en euros, stocké en decimal (string) pour éviter les arrondis */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: "/^\d{1,6}(\.\d{1,2})?$/", message: "Le prix doit être un nombre, ex : 129.99")]
    private ?string $prix = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => self::PRIORITY_NORMAL])]
    #[Assert\Choice(choices: [self::PRIORITY_HIGH, self::PRIORITY_NORMAL, self::PRIORITY_LOW])]
    private int $priorite = self::PRIORITY_NORMAL;

    /** Note visible par les proches (ex : « la version 2024, pas l'ancienne ») */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $notes = null;

    /**
     * Set ajouté par un proche qui voulait offrir un set hors liste.
     * Invisible côté admin tant qu'il n'est pas possédé, pour garder la surprise.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $addedByGiver = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ownedAt = null;

    #[ORM\OneToMany(mappedBy: 'set', targetEntity: Reservation::class, cascade: ['remove'])]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroSet(): ?string
    {
        return $this->numeroSet;
    }

    public function setNumeroSet(?string $numeroSet): static
    {
        $this->numeroSet = SetNumber::normalize($numeroSet);
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom !== null ? trim($nom) : null;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $theme = $theme !== null ? trim($theme) : null;
        $this->theme = $theme ?: null;
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
        $imageUrl = $imageUrl !== null ? trim($imageUrl) : null;
        $this->imageUrl = $imageUrl ?: null;
        return $this;
    }

    /**
     * Image à afficher : celle saisie, sinon l'image publique Rebrickable déduite du numéro.
     */
    public function getDisplayImage(): ?string
    {
        if ($this->imageUrl) {
            return $this->imageUrl;
        }

        return $this->numeroSet ? SetNumber::rebrickableImage($this->numeroSet) : null;
    }

    public function isOwned(): bool
    {
        return $this->owned;
    }

    public function setOwned(bool $owned): static
    {
        if ($owned && !$this->owned) {
            $this->ownedAt = new \DateTimeImmutable();
        } elseif (!$owned) {
            $this->ownedAt = null;
        }
        $this->owned = $owned;
        return $this;
    }

    public function getOwnedAt(): ?\DateTimeImmutable
    {
        return $this->ownedAt;
    }

    public function getPieces(): ?int
    {
        return $this->pieces;
    }

    public function setPieces(?int $pieces): static
    {
        $this->pieces = $pieces;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $prix = $prix !== null ? str_replace([',', ' ', '€'], ['.', '', ''], trim($prix)) : null;
        $this->prix = $prix === '' ? null : $prix;
        return $this;
    }

    public function getPriorite(): int
    {
        return $this->priorite;
    }

    public function setPriorite(int $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }

    public function getPrioriteLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priorite] ?? '';
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $notes = $notes !== null ? trim($notes) : null;
        $this->notes = $notes ?: null;
        return $this;
    }

    public function isAddedByGiver(): bool
    {
        return $this->addedByGiver;
    }

    public function setAddedByGiver(bool $addedByGiver): static
    {
        $this->addedByGiver = $addedByGiver;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setSet($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);
        return $this;
    }

    public function isReserved(): bool
    {
        return !$this->reservations->isEmpty();
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservations->first() ?: null;
    }

    /**
     * Statut vu par un proche. Le statut « réservé » n'est jamais calculé
     * pour l'admin : on passe $revealReservation = false.
     */
    public function getStatus(bool $revealReservation = true): string
    {
        if ($this->owned) {
            return 'owned';
        }
        if ($revealReservation && $this->isReserved()) {
            return 'reserved';
        }
        return 'available';
    }

    public function getLegoUrl(): ?string
    {
        return $this->numeroSet ? 'https://www.lego.com/fr-fr/product/'.rawurlencode($this->numeroSet) : null;
    }

    public function getRebrickableUrl(): ?string
    {
        return $this->numeroSet ? 'https://rebrickable.com/sets/'.rawurlencode(SetNumber::toRebrickable($this->numeroSet)).'/' : null;
    }
}

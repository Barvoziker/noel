<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\UniqueConstraint(name: 'unique_set_reservation', columns: ['set_id'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Set::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Set $set = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $reservedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reservedByHash = null;

    #[ORM\Column(length: 50)]
    private string $anonymousId;

    public function __construct()
    {
        $this->reservedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSet(): ?Set
    {
        return $this->set;
    }

    public function setSet(?Set $set): static
    {
        $this->set = $set;
        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(\DateTimeImmutable $reservedAt): static
    {
        $this->reservedAt = $reservedAt;
        return $this;
    }

    public function getReservedByHash(): ?string
    {
        return $this->reservedByHash;
    }

    public function setReservedByHash(?string $reservedByHash): static
    {
        $this->reservedByHash = $reservedByHash;
        return $this;
    }

    public function getAnonymousId(): string
    {
        return $this->anonymousId;
    }

    public function setAnonymousId(string $anonymousId): static
    {
        $this->anonymousId = $anonymousId;
        return $this;
    }
}

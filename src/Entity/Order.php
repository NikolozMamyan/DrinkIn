<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\UniqueConstraint(name: 'uniq_order_number', columns: ['order_number'])]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $orderNumber = '';

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column(length: 30)]
    private string $deliveryMode = 'express';

    #[ORM\Column]
    private int $subtotalCents = 0;

    #[ORM\Column]
    private int $deliveryFeeCents = 0;

    #[ORM\Column]
    private int $discountCents = 0;

    #[ORM\Column]
    private \DateTimeImmutable $placedAt;

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $guestEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $guestFirstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $guestLastName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $guestPhone = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $guestStreet = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $guestPostalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $guestCity = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $guestCountryCode = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->placedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDeliveryMode(): string
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(string $deliveryMode): self
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getSubtotalCents(): int
    {
        return $this->subtotalCents;
    }

    public function setSubtotalCents(int $subtotalCents): self
    {
        $this->subtotalCents = $subtotalCents;

        return $this;
    }

    public function getDeliveryFeeCents(): int
    {
        return $this->deliveryFeeCents;
    }

    public function setDeliveryFeeCents(int $deliveryFeeCents): self
    {
        $this->deliveryFeeCents = $deliveryFeeCents;

        return $this;
    }

    public function getDiscountCents(): int
    {
        return $this->discountCents;
    }

    public function setDiscountCents(int $discountCents): self
    {
        $this->discountCents = $discountCents;

        return $this;
    }

    public function getPlacedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function setPlacedAt(\DateTimeImmutable $placedAt): self
    {
        $this->placedAt = $placedAt;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function setGuestEmail(?string $guestEmail): self
    {
        $this->guestEmail = $guestEmail;

        return $this;
    }

    public function getGuestFirstName(): ?string
    {
        return $this->guestFirstName;
    }

    public function setGuestFirstName(?string $guestFirstName): self
    {
        $this->guestFirstName = $guestFirstName;

        return $this;
    }

    public function getGuestLastName(): ?string
    {
        return $this->guestLastName;
    }

    public function setGuestLastName(?string $guestLastName): self
    {
        $this->guestLastName = $guestLastName;

        return $this;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function setGuestPhone(?string $guestPhone): self
    {
        $this->guestPhone = $guestPhone;

        return $this;
    }

    public function getGuestStreet(): ?string
    {
        return $this->guestStreet;
    }

    public function setGuestStreet(?string $guestStreet): self
    {
        $this->guestStreet = $guestStreet;

        return $this;
    }

    public function getGuestPostalCode(): ?string
    {
        return $this->guestPostalCode;
    }

    public function setGuestPostalCode(?string $guestPostalCode): self
    {
        $this->guestPostalCode = $guestPostalCode;

        return $this;
    }

    public function getGuestCity(): ?string
    {
        return $this->guestCity;
    }

    public function setGuestCity(?string $guestCity): self
    {
        $this->guestCity = $guestCity;

        return $this;
    }

    public function getGuestCountryCode(): ?string
    {
        return $this->guestCountryCode;
    }

    public function setGuestCountryCode(?string $guestCountryCode): self
    {
        $this->guestCountryCode = $guestCountryCode;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function getTotalCents(): int
    {
        return $this->subtotalCents + $this->deliveryFeeCents - $this->discountCents;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_product_slug', columns: ['slug'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    private string $slug = '';

    #[ORM\Column(length: 180)]
    private string $subtitle = '';

    #[ORM\Column(length: 120)]
    private string $typeLabel = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(nullable: true)]
    private ?int $vintage = null;

    #[ORM\Column]
    private int $volumeCl = 75;

    #[ORM\Column]
    private float $alcoholVolume = 0.0;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column]
    private int $priceCents = 0;

    #[ORM\Column(nullable: true)]
    private ?int $compareAtPriceCents = null;

    #[ORM\Column]
    private float $ratingAverage = 0.0;

    #[ORM\Column]
    private int $ratingCount = 0;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $badgeLabel = null;

    #[ORM\Column(length: 50)]
    private string $icon = 'wine-glass';

    /**
     * @var array<string, int>
     */
    #[ORM\Column]
    private array $tastingProfile = [];

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return $this->typeLabel;
    }

    public function setTypeLabel(string $typeLabel): self
    {
        $this->typeLabel = $typeLabel;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getVintage(): ?int
    {
        return $this->vintage;
    }

    public function setVintage(?int $vintage): self
    {
        $this->vintage = $vintage;

        return $this;
    }

    public function getVolumeCl(): int
    {
        return $this->volumeCl;
    }

    public function setVolumeCl(int $volumeCl): self
    {
        $this->volumeCl = $volumeCl;

        return $this;
    }

    public function getAlcoholVolume(): float
    {
        return $this->alcoholVolume;
    }

    public function setAlcoholVolume(float $alcoholVolume): self
    {
        $this->alcoholVolume = $alcoholVolume;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): self
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getCompareAtPriceCents(): ?int
    {
        return $this->compareAtPriceCents;
    }

    public function setCompareAtPriceCents(?int $compareAtPriceCents): self
    {
        $this->compareAtPriceCents = $compareAtPriceCents;

        return $this;
    }

    public function getRatingAverage(): float
    {
        return $this->ratingAverage;
    }

    public function setRatingAverage(float $ratingAverage): self
    {
        $this->ratingAverage = $ratingAverage;

        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): self
    {
        $this->ratingCount = $ratingCount;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;

        return $this;
    }

    public function getBadgeLabel(): ?string
    {
        return $this->badgeLabel;
    }

    public function setBadgeLabel(?string $badgeLabel): self
    {
        $this->badgeLabel = $badgeLabel;

        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getTastingProfile(): array
    {
        return $this->tastingProfile;
    }

    /**
     * @param array<string, int> $tastingProfile
     */
    public function setTastingProfile(array $tastingProfile): self
    {
        $this->tastingProfile = $tastingProfile;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }
}

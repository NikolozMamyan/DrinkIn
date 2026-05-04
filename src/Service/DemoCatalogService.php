<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DemoCatalogService
{
    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $productsCache = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $categoriesCache = null;

    private bool $schemaAvailable = true;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        if (null !== $this->categoriesCache) {
            return $this->categoriesCache;
        }

        $this->seedIfEmpty();

        if (!$this->schemaAvailable) {
            return $this->categoriesCache = $this->fallbackCategories();
        }

        $normalized = $this->categoryRepository->findCatalogCategoriesWithProductCount();
        array_unshift($normalized, [
            'slug' => 'all',
            'name' => 'Tous',
            'icon' => 'grip',
            'count' => array_sum(array_column($normalized, 'count')),
        ]);

        return $this->categoriesCache = $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        if (null !== $this->productsCache) {
            return $this->productsCache;
        }

        $this->seedIfEmpty();

        if (!$this->schemaAvailable) {
            return $this->productsCache = $this->fallbackProducts();
        }

        return $this->productsCache = array_map(
            fn (Product $product): array => $this->normalizeProduct($product),
            $this->productRepository->findCatalogProducts(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function featuredHero(): array
    {
        return $this->productBySlug('glenfiddich-12');
    }

    /**
     * @return array<string, mixed>
     */
    public function productBySlug(string $slug): array
    {
        $this->seedIfEmpty();

        if (!$this->schemaAvailable) {
            foreach ($this->fallbackProducts() as $product) {
                if ($product['slug'] === $slug) {
                    return $product;
                }
            }

            throw new NotFoundHttpException(sprintf('Unknown product "%s".', $slug));
        }

        $product = $this->productRepository->findOneBy(['slug' => $slug]);
        if (!$product instanceof Product) {
            throw new NotFoundHttpException(sprintf('Unknown product "%s".', $slug));
        }

        return $this->normalizeProduct($product);
    }

    public function seedIfEmpty(): void
    {
        try {
            if (0 !== $this->categoryRepository->count([]) || 0 !== $this->productRepository->count([])) {
                return;
            }
        } catch (TableNotFoundException|ToolsException) {
            $this->schemaAvailable = false;

            return;
        }

        $categories = [];
        foreach ($this->fallbackCategories() as $categoryData) {
            if ('all' === $categoryData['slug']) {
                continue;
            }

            $category = (new Category())
                ->setName($categoryData['name'])
                ->setSlug($categoryData['slug'])
                ->setIcon($categoryData['icon']);

            $categories[$categoryData['slug']] = $category;
            $this->entityManager->persist($category);
        }

        foreach ($this->fallbackProducts() as $productData) {
            $product = (new Product())
                ->setName($productData['name'])
                ->setSlug($productData['slug'])
                ->setSubtitle($productData['subtitle'])
                ->setTypeLabel($productData['type'])
                ->setRegion($productData['region'])
                ->setCountry($productData['country'])
                ->setVolumeCl((int) filter_var($productData['volume'], FILTER_SANITIZE_NUMBER_INT))
                ->setAlcoholVolume((float) str_replace('%', '', $productData['abv']))
                ->setDescription($productData['description'])
                ->setPriceCents($productData['price'])
                ->setCompareAtPriceCents($productData['comparePrice'])
                ->setRatingAverage($productData['rating'])
                ->setRatingCount($productData['ratingCount'])
                ->setFeatured($productData['featured'])
                ->setBadgeLabel($productData['badge'])
                ->setIcon($productData['icon'])
                ->setTastingProfile($productData['tastingProfile'])
                ->setCategory($categories[$productData['category']] ?? null);

            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();
        $this->productsCache = null;
        $this->categoriesCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'slug' => $product->getSlug(),
            'name' => $product->getName(),
            'subtitle' => $product->getSubtitle(),
            'type' => $product->getTypeLabel(),
            'category' => $product->getCategory()?->getSlug() ?? 'all',
            'price' => $product->getPriceCents(),
            'comparePrice' => $product->getCompareAtPriceCents(),
            'rating' => $product->getRatingAverage(),
            'ratingCount' => $product->getRatingCount(),
            'icon' => $product->getIcon(),
            'badge' => $product->getBadgeLabel(),
            'featured' => $product->isFeatured(),
            'region' => $product->getRegion(),
            'country' => $product->getCountry(),
            'image' => $this->productImagePath($product->getSlug(), $product->getCategory()?->getSlug()),
            'volume' => sprintf('%scl', $product->getVolumeCl()),
            'abv' => rtrim(rtrim(number_format($product->getAlcoholVolume(), 1, '.', ''), '0'), '.').'%',
            'description' => $product->getDescription(),
            'tastingProfile' => $product->getTastingProfile(),
        ];
    }

    private function productImagePath(string $slug, ?string $categorySlug): string
    {
        return match ($slug) {
            'aperol' => 'images/products/aperol.png',
            'jack-daniels', 'glenfiddich-12' => 'images/products/jack.png',
            default => match ($categorySlug) {
                'vin' => 'images/products/vin.png',
                'biere' => 'images/products/bierre.png',
                'champagne' => 'images/products/champagne.png',
                'whisky' => 'images/products/jack.png',
                default => 'images/products/vin.png',
            },
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackCategories(): array
    {
        return [
            ['slug' => 'all', 'name' => 'Tous', 'icon' => 'grip', 'count' => 8],
            ['slug' => 'vin', 'name' => 'Vins', 'icon' => 'wine-glass', 'count' => 48],
            ['slug' => 'whisky', 'name' => 'Whisky', 'icon' => 'whiskey-glass', 'count' => 32],
            ['slug' => 'biere', 'name' => 'Bieres', 'icon' => 'beer-mug-empty', 'count' => 60],
            ['slug' => 'champagne', 'name' => 'Champagne', 'icon' => 'champagne-glasses', 'count' => 18],
            ['slug' => 'cocktail', 'name' => 'Cocktails', 'icon' => 'martini-glass-citrus', 'count' => 24],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackProducts(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'bordeaux-aoc',
                'name' => 'Bordeaux AOC',
                'subtitle' => 'Rouge - 2021 - 75cl',
                'type' => 'Rouge',
                'category' => 'vin',
                'price' => 1890,
                'comparePrice' => null,
                'rating' => 4.7,
                'ratingCount' => 84,
                'icon' => 'wine-glass',
                'badge' => 'Top',
                'featured' => true,
                'region' => 'Bordeaux',
                'country' => 'France',
                'volume' => '75cl',
                'abv' => '13.5%',
                'description' => 'Un bordeaux accessible et fruite pour les repas du soir.',
                'tastingProfile' => ['Fruite' => 72, 'Boise' => 48, 'Tourbe' => 0],
            ],
            [
                'id' => 2,
                'slug' => 'glenfiddich-12',
                'name' => 'Glenfiddich 12 ans',
                'subtitle' => 'Single Malt - 70cl',
                'type' => 'Single Malt Scotch',
                'category' => 'whisky',
                'price' => 3360,
                'comparePrice' => 4200,
                'rating' => 4.9,
                'ratingCount' => 128,
                'icon' => 'whiskey-glass',
                'badge' => '-20%',
                'featured' => true,
                'region' => 'Speyside',
                'country' => 'Ecosse',
                'volume' => '70cl',
                'abv' => '40%',
                'description' => 'Single malt emblematique aux notes de poire, creme et chene subtil.',
                'tastingProfile' => ['Fruite' => 85, 'Boise' => 60, 'Tourbe' => 20],
            ],
            [
                'id' => 3,
                'slug' => 'moet-brut',
                'name' => 'Moet Brut',
                'subtitle' => 'Champagne - 75cl',
                'type' => 'Champagne',
                'category' => 'champagne',
                'price' => 5490,
                'comparePrice' => null,
                'rating' => 4.8,
                'ratingCount' => 96,
                'icon' => 'champagne-glasses',
                'badge' => null,
                'featured' => true,
                'region' => 'Champagne',
                'country' => 'France',
                'volume' => '75cl',
                'abv' => '12%',
                'description' => 'Une cuvee vive et festive pour les grands moments.',
                'tastingProfile' => ['Fruite' => 68, 'Boise' => 22, 'Tourbe' => 0],
            ],
            [
                'id' => 4,
                'slug' => 'leffe-brune',
                'name' => 'Leffe Brune',
                'subtitle' => 'Biere - 33cl x 6',
                'type' => 'Abbaye',
                'category' => 'biere',
                'price' => 950,
                'comparePrice' => null,
                'rating' => 4.6,
                'ratingCount' => 42,
                'icon' => 'beer-mug-empty',
                'badge' => null,
                'featured' => true,
                'region' => 'Belgique',
                'country' => 'Belgique',
                'volume' => '6 x 33cl',
                'abv' => '6.5%',
                'description' => 'Biere d abbaye gourmande avec une belle longueur maltee.',
                'tastingProfile' => ['Fruite' => 34, 'Boise' => 20, 'Tourbe' => 0],
            ],
            [
                'id' => 5,
                'slug' => 'chateau-margaux',
                'name' => 'Chateau Margaux',
                'subtitle' => 'Rouge - 2019 - 75cl',
                'type' => 'Grand Cru',
                'category' => 'vin',
                'price' => 2490,
                'comparePrice' => null,
                'rating' => 5.0,
                'ratingCount' => 58,
                'icon' => 'wine-glass',
                'badge' => 'Promo',
                'featured' => false,
                'region' => 'Bordeaux',
                'country' => 'France',
                'volume' => '75cl',
                'abv' => '14%',
                'description' => 'Un rouge ample, structure et precis.',
                'tastingProfile' => ['Fruite' => 76, 'Boise' => 62, 'Tourbe' => 0],
            ],
            [
                'id' => 6,
                'slug' => 'aperol',
                'name' => 'Aperol',
                'subtitle' => 'Liqueur - 75cl',
                'type' => 'Cocktail',
                'category' => 'cocktail',
                'price' => 1450,
                'comparePrice' => null,
                'rating' => 4.5,
                'ratingCount' => 37,
                'icon' => 'martini-glass-citrus',
                'badge' => null,
                'featured' => false,
                'region' => 'Italie',
                'country' => 'Italie',
                'volume' => '75cl',
                'abv' => '11%',
                'description' => 'Base ideale pour des spritz frais et legers.',
                'tastingProfile' => ['Fruite' => 74, 'Boise' => 0, 'Tourbe' => 0],
            ],
            [
                'id' => 7,
                'slug' => 'veuve-clicquot',
                'name' => 'Veuve Clicquot',
                'subtitle' => 'Champagne - 75cl',
                'type' => 'Brut',
                'category' => 'champagne',
                'price' => 6200,
                'comparePrice' => null,
                'rating' => 4.9,
                'ratingCount' => 112,
                'icon' => 'champagne-glasses',
                'badge' => 'New',
                'featured' => false,
                'region' => 'Champagne',
                'country' => 'France',
                'volume' => '75cl',
                'abv' => '12.5%',
                'description' => 'Champagne structure et vif.',
                'tastingProfile' => ['Fruite' => 70, 'Boise' => 28, 'Tourbe' => 0],
            ],
            [
                'id' => 8,
                'slug' => 'jack-daniels',
                'name' => 'Jack Daniel s',
                'subtitle' => 'Tennessee - 70cl',
                'type' => 'Tennessee Whiskey',
                'category' => 'whisky',
                'price' => 2990,
                'comparePrice' => null,
                'rating' => 4.7,
                'ratingCount' => 103,
                'icon' => 'whiskey-glass',
                'badge' => null,
                'featured' => false,
                'region' => 'Tennessee',
                'country' => 'USA',
                'volume' => '70cl',
                'abv' => '40%',
                'description' => 'Profil vanille et rond, valeur sure du catalogue.',
                'tastingProfile' => ['Fruite' => 42, 'Boise' => 58, 'Tourbe' => 6],
            ],
        ];
    }
}

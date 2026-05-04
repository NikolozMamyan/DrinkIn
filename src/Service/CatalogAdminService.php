<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CatalogAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function saveCategory(Category $category): void
    {
        $baseSlug = '' === trim($category->getSlug()) ? $this->slug($category->getName()) : $this->slug($category->getSlug());
        $category->setSlug($this->resolveUniqueCategorySlug($baseSlug, $category));

        if ('' === trim($category->getIcon())) {
            $category->setIcon('wine-glass');
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    /**
     * @param array{fruit: int, wood: int, peat: int, priceEuros: string|int|float, comparePriceEuros: string|int|float|null} $payload
     */
    public function saveProduct(Product $product, array $payload): void
    {
        $baseSlug = '' === trim($product->getSlug()) ? $this->slug($product->getName()) : $this->slug($product->getSlug());
        $product->setSlug($this->resolveUniqueProductSlug($baseSlug, $product));

        $product
            ->setPriceCents($this->eurosToCents($payload['priceEuros']))
            ->setCompareAtPriceCents($this->nullableEurosToCents($payload['comparePriceEuros']))
            ->setTastingProfile([
                'Fruite' => $this->clampPercent($payload['fruit']),
                'Boise' => $this->clampPercent($payload['wood']),
                'Tourbe' => $this->clampPercent($payload['peat']),
            ]);

        if ('' === trim($product->getIcon())) {
            $product->setIcon('wine-glass');
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function deleteCategory(Category $category): void
    {
        if ($category->getProducts()->count() > 0) {
            throw new \LogicException('Impossible de supprimer une categorie qui contient encore des produits.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    public function deleteProduct(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    private function slug(string $value): string
    {
        return $this->slugger->slug(mb_strtolower(trim($value)))->toString();
    }

    private function resolveUniqueCategorySlug(string $baseSlug, Category $category): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (true) {
            $existing = $this->categoryRepository->findOneBy(['slug' => $slug]);
            if (!$existing instanceof Category || $existing->getId() === $category->getId()) {
                return $slug;
            }

            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            ++$suffix;
        }
    }

    private function resolveUniqueProductSlug(string $baseSlug, Product $product): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (true) {
            $existing = $this->productRepository->findOneBy(['slug' => $slug]);
            if (!$existing instanceof Product || $existing->getId() === $product->getId()) {
                return $slug;
            }

            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            ++$suffix;
        }
    }

    private function clampPercent(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function eurosToCents(string|int|float $value): int
    {
        return (int) round((float) str_replace(',', '.', (string) $value) * 100);
    }

    private function nullableEurosToCents(string|int|float|null $value): ?int
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        return $this->eurosToCents($value);
    }
}

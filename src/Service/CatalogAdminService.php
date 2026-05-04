<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CatalogAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function saveCategory(Category $category): void
    {
        if ('' === trim($category->getSlug())) {
            $category->setSlug($this->slug($category->getName()));
        }

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
        if ('' === trim($product->getSlug())) {
            $product->setSlug($this->slug($product->getName()));
        }

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

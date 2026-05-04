<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<array{slug: string, name: string, icon: string, count: int}>
     */
    public function findCatalogCategoriesWithProductCount(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.slug AS slug, c.name AS name, c.icon AS icon, COUNT(p.id) AS productCount')
            ->leftJoin('c.products', 'p')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'icon' => (string) $row['icon'],
            'count' => (int) $row['productCount'],
        ], $rows);
    }
}

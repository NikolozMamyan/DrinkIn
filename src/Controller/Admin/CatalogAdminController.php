<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Form\Admin\CategoryType;
use App\Form\Admin\ProductType;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CatalogAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalogue', name: 'admin_catalog_')]
final class CatalogAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository, ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/catalog/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'products' => $productRepository->findCatalogProducts(),
        ]);
    }

    #[Route('/categories/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function newCategory(Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $catalogAdminService->saveCategory($category);
            $this->addFlash('success', 'Categorie enregistree.');

            return $this->redirectToRoute('admin_catalog_index');
        }

        return $this->render('admin/catalog/category_form.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function editCategory(Category $category, Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $catalogAdminService->saveCategory($category);
            $this->addFlash('success', 'Categorie mise a jour.');

            return $this->redirectToRoute('admin_catalog_index');
        }

        return $this->render('admin/catalog/category_form.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete-category-'.$category->getId(), (string) $request->request->get('_token'))) {
            try {
                $catalogAdminService->deleteCategory($category);
                $this->addFlash('success', 'Categorie supprimee.');
            } catch (\LogicException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('admin_catalog_index');
    }

    #[Route('/products/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $catalogAdminService->saveProduct($product, $this->productPayload($form));
            $this->addFlash('success', 'Produit enregistre.');

            return $this->redirectToRoute('admin_catalog_index');
        }

        return $this->render('admin/catalog/product_form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Product $product, Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $catalogAdminService->saveProduct($product, $this->productPayload($form));
            $this->addFlash('success', 'Produit mis a jour.');

            return $this->redirectToRoute('admin_catalog_index');
        }

        return $this->render('admin/catalog/product_form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request, CatalogAdminService $catalogAdminService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete-product-'.$product->getId(), (string) $request->request->get('_token'))) {
            $catalogAdminService->deleteProduct($product);
            $this->addFlash('success', 'Produit supprime.');
        }

        return $this->redirectToRoute('admin_catalog_index');
    }

    /**
     * @return array{fruit: int, wood: int, peat: int, priceEuros: string|int|float, comparePriceEuros: string|int|float|null}
     */
    private function productPayload($form): array
    {
        return [
            'fruit' => (int) $form->get('fruit')->getData(),
            'wood' => (int) $form->get('wood')->getData(),
            'peat' => (int) $form->get('peat')->getData(),
            'priceEuros' => $form->get('priceEuros')->getData(),
            'comparePriceEuros' => $form->get('comparePriceEuros')->getData(),
        ];
    }
}

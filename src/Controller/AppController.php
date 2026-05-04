<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CartService;
use App\Service\DemoCatalogService;
use App\Service\DemoOrderService;
use App\Service\DemoProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(DemoCatalogService $catalogService, CartService $cartService): Response
    {
        return $this->render('app/home.html.twig', [
            'heroProduct' => $catalogService->featuredHero(),
            'featuredProducts' => array_filter($catalogService->products(), static fn (array $product): bool => $product['featured']),
            'products' => $catalogService->products(),
            'categories' => $catalogService->categories(),
            'cart' => $cartService->getSummary(),
            'current_nav' => 'home',
        ]);
    }

    #[Route('/catalogue', name: 'app_catalogue', methods: ['GET'])]
    public function catalogue(DemoCatalogService $catalogService, CartService $cartService): Response
    {
        return $this->render('app/catalogue.html.twig', [
            'categories' => $catalogService->categories(),
            'products' => $catalogService->products(),
            'cart' => $cartService->getSummary(),
            'current_nav' => 'catalogue',
        ]);
    }

    #[Route('/produits/{slug}', name: 'app_product_show', methods: ['GET'])]
    public function product(string $slug, DemoCatalogService $catalogService, CartService $cartService): Response
    {
        $product = $catalogService->productBySlug($slug);

        return $this->render('app/product.html.twig', [
            'product' => $product,
            'relatedProducts' => array_values(array_filter(
                $catalogService->products(),
                static fn (array $item): bool => $item['category'] === $product['category'] && $item['slug'] !== $product['slug'],
            )),
            'cart' => $cartService->getSummary(),
            'current_nav' => 'catalogue',
        ]);
    }

    #[Route('/panier', name: 'app_cart', methods: ['GET'])]
    public function cart(CartService $cartService): Response
    {
        return $this->render('app/cart.html.twig', [
            'cart' => $cartService->getSummary(),
            'current_nav' => 'cart',
        ]);
    }

    #[Route('/paiement', name: 'app_checkout', methods: ['GET'])]
    public function checkout(CartService $cartService, DemoProfileService $profileService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('app/checkout.html.twig', [
            'cart' => $cartService->getSummary(),
            'profile' => $profileService->build($this->getUser()),
            'current_nav' => 'cart',
        ]);
    }

    #[Route('/commandes', name: 'app_orders', methods: ['GET'])]
    public function orders(DemoOrderService $orderService, CartService $cartService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $orders = $orderService->orders($this->getUser());
        $currentOrder = $orders[0] ?? null;

        return $this->render('app/orders.html.twig', [
            'orders' => $orders,
            'currentOrder' => $currentOrder,
            'timeline' => null !== $currentOrder ? $orderService->timeline($currentOrder['number'], $this->getUser()) : [],
            'cart' => $cartService->getSummary(),
            'current_nav' => 'orders',
        ]);
    }

    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function profile(DemoProfileService $profileService, CartService $cartService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('app/profile.html.twig', [
            'profile' => $profileService->build($this->getUser()),
            'cart' => $cartService->getSummary(),
            'current_nav' => 'profile',
        ]);
    }
}

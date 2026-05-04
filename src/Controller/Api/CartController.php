<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CartService;
use App\Service\MoneyFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cart', name: 'api_cart_')]
final class CartController extends AbstractController
{
    #[Route('/items', name: 'add_item', methods: ['POST'])]
    public function addItem(Request $request, CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $summary = $cartService->addItem((int) ($payload['productId'] ?? 0), (int) ($payload['quantity'] ?? 1));

        return $this->json($this->normalizeSummary($summary, $moneyFormatter));
    }

    #[Route('/items/{productId}', name: 'update_item', methods: ['PATCH', 'DELETE'])]
    public function updateItem(int $productId, Request $request, CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        $quantity = 0;
        if ('PATCH' === $request->getMethod()) {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $quantity = (int) ($payload['quantity'] ?? 1);
        }

        $summary = $cartService->updateQuantity($productId, $quantity);

        return $this->json($this->normalizeSummary($summary, $moneyFormatter));
    }

    #[Route('/delivery', name: 'delivery', methods: ['PATCH'])]
    public function delivery(Request $request, CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $summary = $cartService->updateDelivery((string) ($payload['mode'] ?? 'express'));

        return $this->json($this->normalizeSummary($summary, $moneyFormatter));
    }

    #[Route('/promo', name: 'promo', methods: ['POST'])]
    public function promo(Request $request, CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $summary = $cartService->applyPromo((string) ($payload['code'] ?? ''));

        return $this->json($this->normalizeSummary($summary, $moneyFormatter) + [
            'promoApplied' => null !== $summary['promo'],
        ]);
    }

    #[Route('/favorites/{slug}', name: 'favorite', methods: ['POST'])]
    public function favorite(string $slug, CartService $cartService): JsonResponse
    {
        return $this->json([
            'favorite' => $cartService->toggleFavorite($slug),
        ]);
    }

    #[Route('/note', name: 'note', methods: ['PATCH'])]
    public function note(Request $request, CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $summary = $cartService->updateNote((string) ($payload['note'] ?? ''));

        return $this->json($this->normalizeSummary($summary, $moneyFormatter));
    }

    #[Route('', name: 'clear', methods: ['DELETE'])]
    public function clear(CartService $cartService, MoneyFormatter $moneyFormatter): JsonResponse
    {
        return $this->json($this->normalizeSummary($cartService->clearSummary(), $moneyFormatter));
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    private function normalizeSummary(array $summary, MoneyFormatter $moneyFormatter): array
    {
        return [
            'count' => $summary['count'],
            'delivery' => $summary['delivery'],
            'promo' => $summary['promo'],
            'note' => $summary['note'],
            'isEmpty' => $summary['isEmpty'],
            'subtotal' => $moneyFormatter->euros($summary['subtotal']),
            'deliveryFee' => $moneyFormatter->euros($summary['deliveryFee']),
            'discount' => $moneyFormatter->euros($summary['discount']),
            'total' => $moneyFormatter->euros($summary['total']),
            'totalCents' => $summary['total'],
            'favorites' => $summary['favorites'],
            'items' => array_map(static fn (array $item): array => [
                'id' => $item['id'],
                'quantity' => $item['quantity'],
                'lineTotalCents' => $item['lineTotal'],
            ], $summary['items']),
        ];
    }
}

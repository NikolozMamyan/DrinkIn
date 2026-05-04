<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class CartService
{
    private const SESSION_KEY = 'drinkin.cart';
    private const PROMO_CODE = 'BIENVENUE10';
    private const DEFAULT_STATE = [
        'items' => [],
        'delivery' => 'express',
        'promo' => null,
        'note' => '',
        'favorites' => [],
    ];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $productIndex = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DemoCatalogService $catalogService,
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $state = $this->getState();
        $catalog = $this->productsById();

        $products = [];
        $subtotal = 0;
        $count = 0;

        foreach ($state['items'] as $productId => $quantity) {
            $product = $catalog[(int) $productId] ?? null;
            if (null !== $product) {
                $lineTotal = $product['price'] * $quantity;
                $products[] = $product + [
                    'quantity' => $quantity,
                    'lineTotal' => $lineTotal,
                ];
                $subtotal += $lineTotal;
                $count += $quantity;
            }
        }

        $deliveryFee = 0 === $count
            ? 0
            : match ($state['delivery']) {
                'standard', 'pickup' => 0,
                default => 390,
            };
        $discount = self::PROMO_CODE === $state['promo'] ? (int) round($subtotal * 0.10) : 0;

        return [
            'items' => $products,
            'count' => $count,
            'delivery' => $state['delivery'],
            'promo' => $state['promo'],
            'note' => $state['note'],
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'discount' => $discount,
            'total' => $subtotal + $deliveryFee - $discount,
            'favorites' => $state['favorites'],
            'isEmpty' => 0 === $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function addItem(int $productId, int $quantity = 1): array
    {
        $state = $this->getState();
        $state['items'][$productId] = max(1, ($state['items'][$productId] ?? 0) + $quantity);
        $this->saveState($state);

        return $this->getSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateQuantity(int $productId, int $quantity): array
    {
        $state = $this->getState();

        if ($quantity <= 0) {
            unset($state['items'][$productId]);
        } else {
            $state['items'][$productId] = $quantity;
        }

        $this->saveState($state);

        return $this->getSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDelivery(string $mode): array
    {
        $state = $this->getState();
        $state['delivery'] = in_array($mode, ['express', 'standard', 'pickup'], true) ? $mode : 'express';
        $this->saveState($state);

        return $this->getSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function applyPromo(string $code): array
    {
        $state = $this->getState();
        $state['promo'] = strtoupper(trim($code)) === self::PROMO_CODE ? self::PROMO_CODE : null;
        $this->saveState($state);

        return $this->getSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateNote(string $note): array
    {
        $state = $this->getState();
        $state['note'] = trim($note);
        $this->saveState($state);

        return $this->getSummary();
    }

    public function clear(): void
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $cart = $this->getOrCreatePersistentCart($user);
            foreach ($cart->getItems()->toArray() as $item) {
                $cart->removeItem($item);
            }
            $cart->setPromoCode(null)->setNote('');
            $this->entityManager->flush();
        }

        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function clearSummary(): array
    {
        $this->clear();

        return $this->getSummary();
    }

    public function toggleFavorite(string $slug): bool
    {
        $state = $this->getState();
        $favorites = $state['favorites'] ?? [];

        if (in_array($slug, $favorites, true)) {
            $favorites = array_values(array_filter($favorites, static fn (string $favorite): bool => $favorite !== $slug));
            $active = false;
        } else {
            $favorites[] = $slug;
            $favorites = array_values(array_unique($favorites));
            $active = true;
        }

        $state['favorites'] = $favorites;
        $this->saveState($state);

        return $active;
    }

    /**
     * @return array<string, mixed>
     */
    private function getState(): array
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->mergeSessionIntoPersistentCart($user);

            return $this->persistentState($this->getOrCreatePersistentCart($user));
        }

        $state = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

        return array_replace(self::DEFAULT_STATE, $state);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state): void
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $cart = $this->getOrCreatePersistentCart($user);
            $cart->setDeliveryMode((string) $state['delivery']);
            $cart->setPromoCode($state['promo'] ? (string) $state['promo'] : null);
            $cart->setNote((string) $state['note']);
            $cart->setFavorites($state['favorites'] ?? []);

            $byProductId = [];
            foreach ($cart->getItems()->toArray() as $item) {
                $productId = $item->getProduct()?->getId();
                if (null !== $productId) {
                    $byProductId[$productId] = $item;
                }
            }

            foreach ($state['items'] as $productId => $quantity) {
                $productId = (int) $productId;
                if (isset($byProductId[$productId])) {
                    $byProductId[$productId]->setQuantity((int) $quantity);
                    unset($byProductId[$productId]);
                    continue;
                }

                $product = $this->productRepository->find($productId);
                if (!$product instanceof Product) {
                    continue;
                }

                $cart->addItem((new CartItem())
                    ->setProduct($product)
                    ->setQuantity((int) $quantity));
            }

            foreach ($byProductId as $orphanItem) {
                $cart->removeItem($orphanItem);
            }

            $this->entityManager->flush();
            $this->requestStack->getSession()->remove(self::SESSION_KEY);

            return;
        }

        $this->requestStack->getSession()->set(self::SESSION_KEY, array_replace(self::DEFAULT_STATE, $state));
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function getOrCreatePersistentCart(User $user): Cart
    {
        $cart = $this->cartRepository->findOneByUser($user);
        if ($cart instanceof Cart) {
            return $cart;
        }

        $cart = (new Cart())->setUser($user);
        $user->setCart($cart);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    private function mergeSessionIntoPersistentCart(User $user): void
    {
        $session = $this->requestStack->getSession();
        $sessionState = $session->get(self::SESSION_KEY, []);
        if ([] === $sessionState) {
            return;
        }

        $cart = $this->getOrCreatePersistentCart($user);
        $currentState = $this->persistentState($cart);
        $mergedItems = $currentState['items'];

        foreach ($sessionState['items'] ?? [] as $productId => $quantity) {
            $mergedItems[(int) $productId] = (($mergedItems[(int) $productId] ?? 0) + (int) $quantity);
        }

        $merged = [
            'items' => $mergedItems,
            'delivery' => $sessionState['delivery'] ?? $currentState['delivery'],
            'promo' => $sessionState['promo'] ?? $currentState['promo'],
            'note' => $sessionState['note'] ?? $currentState['note'],
            'favorites' => array_values(array_unique(array_merge($currentState['favorites'], $sessionState['favorites'] ?? []))),
        ];

        $this->saveState($merged);
        $session->remove(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function persistentState(Cart $cart): array
    {
        $items = [];
        foreach ($cart->getItems() as $item) {
            $productId = $item->getProduct()?->getId();
            if (null !== $productId) {
                $items[$productId] = $item->getQuantity();
            }
        }

        return [
            'items' => $items,
            'delivery' => $cart->getDeliveryMode(),
            'promo' => $cart->getPromoCode(),
            'note' => $cart->getNote(),
            'favorites' => $cart->getFavorites(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productsById(): array
    {
        if (null !== $this->productIndex) {
            return $this->productIndex;
        }

        $index = [];
        foreach ($this->catalogService->products() as $product) {
            $index[(int) $product['id']] = $product;
        }

        return $this->productIndex = $index;
    }
}

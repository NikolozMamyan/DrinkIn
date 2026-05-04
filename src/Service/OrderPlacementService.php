<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class OrderPlacementService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function place(User $user): Order
    {
        $cart = $this->cartService->getSummary();
        if ([] === $cart['items']) {
            throw new \RuntimeException('Cannot place an order from an empty cart.');
        }

        $order = (new Order())
            ->setOrderNumber($this->generateOrderNumber())
            ->setStatus('pending')
            ->setDeliveryMode($cart['delivery'])
            ->setSubtotalCents($cart['subtotal'])
            ->setDeliveryFeeCents($cart['deliveryFee'])
            ->setDiscountCents($cart['discount'])
            ->setPlacedAt(new \DateTimeImmutable())
            ->setUser($user);

        foreach ($cart['items'] as $item) {
            $orderItem = (new OrderItem())
                ->setProductName($item['name'])
                ->setSubtitle($item['subtitle'])
                ->setIcon($item['icon'])
                ->setQuantity($item['quantity'])
                ->setUnitPriceCents($item['price']);

            $order->addItem($orderItem);
        }

        $user->setLoyaltyPoints($user->getLoyaltyPoints() + (int) floor($cart['total'] / 100));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->cartService->clear();

        return $order;
    }

    private function generateOrderNumber(): string
    {
        return sprintf('DRK-%s%04d', (new \DateTimeImmutable())->format('Ymd'), random_int(1000, 9999));
    }
}

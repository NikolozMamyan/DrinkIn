<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderRepository;

final class DemoOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MoneyFormatter $moneyFormatter,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orders(?User $user): array
    {
        if ($user instanceof User) {
            $orders = $this->orderRepository->findRecentForUser($user);

            return array_map(fn (Order $order): array => $this->normalizeOrder($order), $orders);
        }

        return [];
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    public function timeline(string $number, ?User $user = null): array
    {
        if ($user instanceof User) {
            $order = $this->orderRepository->findOneByNumberAndUser($number, $user);
            if ($order instanceof Order) {
                return $this->buildTimeline($order->getStatus());
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderDetail(string $number, ?User $user = null): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        $order = $this->orderRepository->findOneByNumberAndUser($number, $user);
        if (!$order instanceof Order) {
            return null;
        }

        $normalized = $this->normalizeOrder($order);
        $normalized['timeline'] = $this->buildTimeline($order->getStatus());
        $normalized['rating'] = $order->getRating();

        return $normalized;
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    private function buildTimeline(string $status): array
    {
        $delivered = 'delivered' === $status;

        return [
            ['label' => 'Commande recue', 'time' => '19:42 - Confirmation envoyee', 'done' => true, 'active' => false, 'icon' => 'check'],
            ['label' => 'Preparation terminee', 'time' => '19:51 - Votre commande est prete', 'done' => true, 'active' => false, 'icon' => 'check'],
            ['label' => 'En cours de livraison', 'time' => $delivered ? '20:03 - Livreur arrive' : '20:03 - Arrivee estimee 20:18', 'done' => $delivered, 'active' => !$delivered, 'icon' => 'motorcycle'],
            ['label' => 'Livre', 'time' => $delivered ? '20:18 - Commande livree' : 'En attente...', 'done' => $delivered, 'active' => false, 'icon' => 'house'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOrder(Order $order): array
    {
        $items = $order->getItems()->toArray();
        $firstItem = $items[0] ?? null;

        return [
            'number' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'statusLabel' => $this->statusLabel($order->getStatus()),
            'icon' => $firstItem instanceof OrderItem ? $firstItem->getIcon() : 'box',
            'itemsLabel' => $this->buildItemsLabel($items),
            'dateLabel' => $order->getPlacedAt()->format('d M').' - '.$this->moneyFormatter->euros($order->getTotalCents()),
            'etaLabel' => 'pending' === $order->getStatus() ? '~15 min' : null,
            'amount' => $order->getTotalCents(),
        ];
    }

    /**
     * @param list<OrderItem> $items
     */
    private function buildItemsLabel(array $items): string
    {
        if ([] === $items) {
            return 'Commande DrinkIn';
        }

        return implode(' - ', array_map(
            static fn (OrderItem $item): string => $item->getProductName().($item->getQuantity() > 1 ? sprintf(' x %d', $item->getQuantity()) : ''),
            array_slice($items, 0, 2),
        ));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'delivered' => 'Livre',
            'cancelled' => 'Annule',
            default => 'En route',
        };
    }
}

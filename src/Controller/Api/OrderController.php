<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\DemoOrderService;
use App\Service\OrderPlacementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders', name: 'api_orders_')]
final class OrderController extends AbstractController
{
    #[Route('/{number}', name: 'detail', methods: ['GET'])]
    public function detail(string $number, DemoOrderService $orderService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $detail = $orderService->orderDetail($number, $this->getUser());
        if (null === $detail) {
            return $this->json(['ok' => false], 404);
        }

        return $this->json([
            'ok' => true,
            'order' => $detail,
        ]);
    }

    #[Route('/checkout', name: 'checkout', methods: ['POST'])]
    public function checkout(OrderPlacementService $orderPlacementService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        try {
            $order = $orderPlacementService->place($user);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return $this->json([
            'ok' => true,
            'orderNumber' => $order->getOrderNumber(),
            'totalCents' => $order->getTotalCents(),
        ]);
    }

    #[Route('/{number}/rating', name: 'rating', methods: ['POST'])]
    public function rating(string $number, Request $request, OrderRepository $orderRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $order = $orderRepository->findOneByNumberAndUser($number, $user);

        if (null === $order) {
            return $this->json(['ok' => false], 404);
        }

        $order->setRating(max(1, min(5, (int) ($payload['rating'] ?? 5))));
        $entityManager->flush();

        return $this->json([
            'orderNumber' => $number,
            'rating' => $order->getRating(),
            'ok' => true,
        ]);
    }
}

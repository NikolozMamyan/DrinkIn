<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\AuthCookieFactory;
use App\Service\DemoOrderService;
use App\Service\OrderPlacementService;
use App\Service\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    public function checkout(Request $request, OrderPlacementService $orderPlacementService): JsonResponse
    {
        $user = $this->getUser();
        $payload = [];

        if (!$user instanceof User) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $payload = [];
            }
        }

        try {
            $order = $orderPlacementService->place($user instanceof User ? $user : null, is_array($payload) ? $payload : []);
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
            'guestCheckout' => null === $order->getUser(),
        ]);
    }

    #[Route('/guest-account', name: 'guest_account', methods: ['POST'])]
    public function guestAccount(
        Request $request,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        OrderPlacementService $orderPlacementService,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        $orderNumber = trim((string) ($payload['orderNumber'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $orderNumber || !$orderPlacementService->isRememberedGuestOrder($orderNumber)) {
            return $this->json(['ok' => false, 'message' => 'Commande invitee introuvable ou expiree.'], 404);
        }

        /** @var Order|null $order */
        $order = $orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        if (!$order instanceof Order || !$orderPlacementService->canPromoteGuestOrder($order)) {
            return $this->json(['ok' => false, 'message' => 'Cette commande ne peut plus etre transformee en compte.'], 422);
        }

        if (strlen($password) < 8) {
            return $this->json(['ok' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caracteres.'], 422);
        }

        $email = $order->getGuestEmail();
        if (!is_string($email) || '' === $email) {
            return $this->json(['ok' => false, 'message' => 'Email invite introuvable.'], 422);
        }

        if ($userRepository->findOneBy(['email' => $email]) instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Un compte existe deja avec cet email.'], 409);
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($order->getGuestFirstName() ?? '')
            ->setLastName($order->getGuestLastName() ?? '')
            ->setPhone($order->getGuestPhone())
            ->setRoles(['ROLE_USER'])
            ->setLoyaltyPoints(0);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        $orderPlacementService->promoteGuestOrderToUser($order, $user);

        [$session, $plainToken, $deviceId] = $sessionManager->createSession($user);

        $response = $this->json([
            'ok' => true,
            'redirectUrl' => $this->generateUrl('app_orders'),
            'userId' => $user->getId(),
        ]);
        $response->headers->setCookie($authCookieFactory->buildAuthCookie($request, $plainToken, $session->getExpiresAt()));
        $response->headers->setCookie($authCookieFactory->buildDeviceCookie($request, $deviceId));

        return $response;
    }

    #[Route('/{number}/rating', name: 'rating', methods: ['POST'])]
    public function rating(string $number, Request $request, OrderRepository $orderRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->decodePayload($request);
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

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestException('Invalid JSON payload.');
        }

        return is_array($payload) ? $payload : [];
    }
}

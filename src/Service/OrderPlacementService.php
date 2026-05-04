<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrderPlacementService
{
    public const GUEST_ORDER_SESSION_KEY = 'drinkin.guest_order_number';

    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $guestData
     */
    public function place(?User $user, array $guestData = []): Order
    {
        $cart = $this->cartService->getSummary();
        if ([] === $cart['items']) {
            throw new \RuntimeException('Cannot place an order from an empty cart.');
        }

        if (!$user instanceof User) {
            $guestData = $this->validateGuestData($guestData);
        }

        $order = (new Order())
            ->setOrderNumber($this->generateOrderNumber())
            ->setStatus('pending')
            ->setDeliveryMode($cart['delivery'])
            ->setSubtotalCents($cart['subtotal'])
            ->setDeliveryFeeCents($cart['deliveryFee'])
            ->setDiscountCents($cart['discount'])
            ->setPlacedAt(new \DateTimeImmutable());

        if ($user instanceof User) {
            $order->setUser($user);
        } else {
            $order
                ->setGuestEmail($guestData['email'])
                ->setGuestFirstName($guestData['firstName'])
                ->setGuestLastName($guestData['lastName'])
                ->setGuestPhone($guestData['phone'])
                ->setGuestStreet($guestData['street'])
                ->setGuestPostalCode($guestData['postalCode'])
                ->setGuestCity($guestData['city'])
                ->setGuestCountryCode($guestData['countryCode']);
        }

        foreach ($cart['items'] as $item) {
            $orderItem = (new OrderItem())
                ->setProductName($item['name'])
                ->setSubtitle($item['subtitle'])
                ->setIcon($item['icon'])
                ->setQuantity($item['quantity'])
                ->setUnitPriceCents($item['price']);

            $order->addItem($orderItem);
        }

        if ($user instanceof User) {
            $user->setLoyaltyPoints($user->getLoyaltyPoints() + (int) floor($cart['total'] / 100));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        if (!$user instanceof User) {
            $this->rememberGuestOrder($order);
        }

        $this->cartService->clear();

        return $order;
    }

    public function canPromoteGuestOrder(Order $order): bool
    {
        return null === $order->getUser() && null !== $order->getGuestEmail();
    }

    public function promoteGuestOrderToUser(Order $order, User $user): void
    {
        $order->setUser($user);

        if (null === $user->getPhone() && null !== $order->getGuestPhone()) {
            $user->setPhone($order->getGuestPhone());
        }

        if ($user->getAddresses()->count() === 0 && null !== $order->getGuestStreet() && null !== $order->getGuestPostalCode() && null !== $order->getGuestCity()) {
            $address = (new Address())
                ->setLabel('Adresse principale')
                ->setStreet($order->getGuestStreet())
                ->setPostalCode($order->getGuestPostalCode())
                ->setCity($order->getGuestCity())
                ->setCountryCode($order->getGuestCountryCode() ?? 'FR')
                ->setIcon('house')
                ->setIsDefault(true);
            $user->addAddress($address);
            $this->entityManager->persist($address);
        }

        $user->setLoyaltyPoints($user->getLoyaltyPoints() + (int) floor($order->getTotalCents() / 100));

        $order
            ->setGuestEmail(null)
            ->setGuestFirstName(null)
            ->setGuestLastName(null)
            ->setGuestPhone(null)
            ->setGuestStreet(null)
            ->setGuestPostalCode(null)
            ->setGuestCity(null)
            ->setGuestCountryCode(null);

        $this->clearRememberedGuestOrder($order->getOrderNumber());
        $this->entityManager->flush();
    }

    public function isRememberedGuestOrder(string $orderNumber): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return false;
        }

        return $request->getSession()->get(self::GUEST_ORDER_SESSION_KEY) === $orderNumber;
    }

    /**
     * @param array<string, mixed> $guestData
     * @return array{email:string,firstName:string,lastName:string,phone:?string,street:string,postalCode:string,city:string,countryCode:string}
     */
    private function validateGuestData(array $guestData): array
    {
        $normalized = [
            'email' => mb_strtolower(trim((string) ($guestData['email'] ?? ''))),
            'firstName' => trim((string) ($guestData['firstName'] ?? '')),
            'lastName' => trim((string) ($guestData['lastName'] ?? '')),
            'phone' => $this->normalizeOptionalString($guestData['phone'] ?? null),
            'street' => trim((string) ($guestData['street'] ?? '')),
            'postalCode' => trim((string) ($guestData['postalCode'] ?? '')),
            'city' => trim((string) ($guestData['city'] ?? '')),
            'countryCode' => strtoupper(trim((string) ($guestData['countryCode'] ?? 'FR'))),
        ];

        $violations = $this->validator->validate($normalized, new Assert\Collection(
            fields: [
                'email' => new Assert\Required([new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]),
                'firstName' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 100)]),
                'lastName' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 100)]),
                'phone' => new Assert\Optional([new Assert\Length(max: 30)]),
                'street' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 160)]),
                'postalCode' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 20)]),
                'city' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 120)]),
                'countryCode' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 2)]),
            ],
            allowExtraFields: true,
        ));

        if (count($violations) > 0) {
            throw new \RuntimeException($violations[0]?->getMessage() ?? 'Informations de livraison invalides.');
        }

        return $normalized;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    private function rememberGuestOrder(Order $order): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set(self::GUEST_ORDER_SESSION_KEY, $order->getOrderNumber());
    }

    private function clearRememberedGuestOrder(string $orderNumber): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return;
        }

        if ($request->getSession()->get(self::GUEST_ORDER_SESSION_KEY) === $orderNumber) {
            $request->getSession()->remove(self::GUEST_ORDER_SESSION_KEY);
        }
    }

    private function generateOrderNumber(): string
    {
        return sprintf('DRK-%s%04d', (new \DateTimeImmutable())->format('Ymd'), random_int(1000, 9999));
    }
}

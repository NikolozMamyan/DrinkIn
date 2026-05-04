<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\DemoCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CheckoutOrderTest extends WebTestCase
{
    public function testCheckoutCreatesAnOrderForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $container->get(DemoCatalogService::class)->seedIfEmpty();

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail('checkout@test.local')
            ->setPassword($passwordHasher->hashPassword(new User(), 'Sup3rSecret!42'))
            ->setFirstName('Checkout')
            ->setLastName('Tester')
            ->setRoles(['ROLE_USER'])
            ->setLoyaltyPoints(0);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'checkout@test.local',
            'password' => 'Sup3rSecret!42',
            'remember' => true,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/cart/items', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => 1,
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/orders/checkout');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['ok']);
        self::assertStringStartsWith('DRK-', $payload['orderNumber']);
        self::assertGreaterThan(0, $payload['totalCents']);
    }

    public function testGuestCheckoutCreatesOrderAndCanBePromotedToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $container->get(DemoCatalogService::class)->seedIfEmpty();

        $client->request('POST', '/api/cart/items', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => 1,
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/orders/checkout', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'guest-order@test.local',
            'firstName' => 'Guest',
            'lastName' => 'Buyer',
            'phone' => '0600000000',
            'street' => '1 rue du Test',
            'postalCode' => '75001',
            'city' => 'Paris',
            'countryCode' => 'FR',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertTrue($payload['guestCheckout']);

        /** @var OrderRepository $orderRepository */
        $orderRepository = $container->get(OrderRepository::class);
        $order = $orderRepository->findOneBy(['orderNumber' => $payload['orderNumber']]);
        self::assertNotNull($order);
        self::assertNull($order->getUser());
        self::assertSame('guest-order@test.local', $order->getGuestEmail());

        $client->request('POST', '/api/orders/guest-account', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'orderNumber' => $payload['orderNumber'],
            'password' => 'Sup3rSecret!42',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'guest-order@test.local']);
        self::assertInstanceOf(User::class, $user);

        $order = $orderRepository->findOneBy(['orderNumber' => $payload['orderNumber']]);
        self::assertNotNull($order);
        self::assertSame($user->getId(), $order->getUser()?->getId());
        self::assertNotNull($client->getCookieJar()->get('AUTH_TOKEN'));
    }

    public function testGuestAccountRejectsInvalidJsonPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/orders/guest-account', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        self::assertResponseStatusCodeSame(400);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\DemoCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartApiTest extends WebTestCase
{
    public function testAddItemReturnsUpdatedSummary(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/cart/items', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => 1,
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['count']);
        self::assertArrayHasKey('total', $payload);
    }

    public function testAuthenticatedCartIsPersistedInDatabase(): void
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

        $user = (new User())
            ->setEmail('cart@test.local')
            ->setPassword('not-used')
            ->setFirstName('Cart')
            ->setLastName('Tester')
            ->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('POST', '/api/cart/items', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => 1,
            'quantity' => 2,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        /** @var CartRepository $cartRepository */
        $cartRepository = $container->get(CartRepository::class);
        $cart = $cartRepository->findOneByUser($user);

        self::assertNotNull($cart);
        self::assertCount(1, $cart->getItems());
        self::assertSame(2, $cart->getItems()->first()->getQuantity());
        self::assertSame(1, $cart->getItems()->first()->getProduct()?->getId());
    }

    public function testCartCanBeCleared(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/cart/items', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => 1,
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('DELETE', '/api/cart');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['isEmpty']);
        self::assertSame(0, $payload['count']);
        self::assertSame('0,00EUR', $payload['total']);
    }
}

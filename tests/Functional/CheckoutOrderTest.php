<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Service\DemoCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $user = (new User())
            ->setEmail('checkout@test.local')
            ->setPassword('not-used')
            ->setFirstName('Checkout')
            ->setLastName('Tester')
            ->setRoles(['ROLE_USER'])
            ->setLoyaltyPoints(0);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
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
}

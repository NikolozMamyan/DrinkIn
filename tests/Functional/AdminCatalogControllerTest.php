<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminCatalogControllerTest extends WebTestCase
{
    public function testAdminCatalogRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/catalogue');

        self::assertResponseRedirects('/connexion');
    }

    public function testAdminCanAccessCatalogBackOffice(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $admin = (new User())
            ->setEmail('admin@test.local')
            ->setPassword('not-used')
            ->setFirstName('Admin')
            ->setLastName('User')
            ->setRoles(['ROLE_ADMIN']);

        $entityManager->persist($admin);
        $entityManager->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/catalogue');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catalogue');
    }
}

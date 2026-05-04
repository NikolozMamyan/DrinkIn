<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileApiTest extends WebTestCase
{
    public function testProfileAndAddressesCanBeUpdated(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = (new User())
            ->setEmail('profile@test.local')
            ->setPassword('not-used')
            ->setFirstName('Old')
            ->setLastName('Name')
            ->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('PATCH', '/api/profile', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'firstName' => 'Nina',
            'lastName' => 'Martin',
            'email' => 'nina@test.local',
            'phone' => '0600000042',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/profile/addresses', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'label' => 'Maison',
            'street' => '1 rue de Paris',
            'postalCode' => '75001',
            'city' => 'Paris',
            'countryCode' => 'FR',
            'icon' => 'house',
            'isDefault' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        /** @var AddressRepository $addressRepository */
        $addressRepository = $container->get(AddressRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'nina@test.local']);
        $address = $addressRepository->findOneBy(['user' => $user]);

        self::assertNotNull($user);
        self::assertSame('Nina', $user->getFirstName());
        self::assertSame('Martin', $user->getLastName());
        self::assertSame('nina@test.local', $user->getEmail());
        self::assertSame('0600000042', $user->getPhone());
        self::assertNotNull($address);
        self::assertSame('Maison', $address->getLabel());
        self::assertTrue($address->isDefault());
    }
}

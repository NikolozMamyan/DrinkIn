<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserSessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityFlowTest extends WebTestCase
{
    public function testGuestThemePreferencePersistsAcrossRequests(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/preferences/theme',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['darkModeEnabled' => false], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame('light', $client->getCookieJar()->get('drinkin_theme')?->getValue());

        $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('theme-soft-light', (string) $client->getResponse()->getContent());
        self::assertSame('light', $client->getRequest()->getSession()->get('drinkin_theme'));
    }

    public function testRegistrationLogsUserInAndRedirectsToCatalogue(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('drinkin_theme', 'light'));
        $crawler = $client->request('GET', '/inscription');

        $client->submitForm('Creer mon compte', [
            'form[firstName]' => 'Nina',
            'form[lastName]' => 'Martin',
            'form[email]' => 'nina-register@test.local',
            'form[phone]' => '0601020304',
            'form[plainPassword][first]' => 'Sup3rSecret!42',
            'form[plainPassword][second]' => 'Sup3rSecret!42',
        ]);

        self::assertResponseRedirects('/catalogue');

        $client->followRedirect();
        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'nina-register@test.local']);

        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isDarkModeEnabled());
        self::assertSame('light', $client->getCookieJar()->get('drinkin_theme')?->getValue());
        self::assertNotNull($client->getCookieJar()->get('AUTH_TOKEN'));
        self::assertNotNull($client->getCookieJar()->get('DEVICE_ID'));
    }

    public function testLoginAndLogoutManagePersistentSessions(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail('session@test.local')
            ->setFirstName('Session')
            ->setLastName('Tester')
            ->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'Sup3rSecret!42'));

        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'session@test.local',
            'password' => 'Sup3rSecret!42',
            'remember' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertNotNull($client->getCookieJar()->get('AUTH_TOKEN'));

        /** @var UserSessionRepository $sessionRepository */
        $sessionRepository = $container->get(UserSessionRepository::class);
        self::assertCount(1, $sessionRepository->findAll());

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/logout');
        self::assertResponseIsSuccessful();
        self::assertNull($client->getCookieJar()->get('AUTH_TOKEN'));
        self::assertNull($client->getCookieJar()->get('DEVICE_ID'));

        $storedSession = $sessionRepository->findAll()[0];
        self::assertTrue($storedSession->isRevoked());

        $client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion');
    }
}

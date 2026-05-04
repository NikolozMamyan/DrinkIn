<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AppControllerTest extends WebTestCase
{
    public function testHomePageRemainsReachableWithoutDatabaseSchema(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testPublicPagesAreReachable(): void
    {
        $client = static::createClient();

        foreach (['/', '/catalogue', '/panier', '/connexion', '/inscription', '/produits/glenfiddich-12'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful(sprintf('Expected "%s" to be reachable.', $path));
        }
    }

    public function testProtectedPagesRedirectGuestsToLogin(): void
    {
        $client = static::createClient();

        foreach (['/profil', '/commandes', '/paiement'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/connexion');
        }
    }
}

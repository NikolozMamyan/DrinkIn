<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserSession;
use App\Service\AuthCookieFactory;
use App\Service\DeviceIdentifier;
use App\Service\SessionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const BYPASS_PREFIXES = [
        '/icons/',
        '/assets/',
        '/build/',
        '/_wdt',
        '/_profiler',
    ];

    private const BYPASS_EXACT_PATHS = [
        '/manifest.webmanifest',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/sw.js',
    ];

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly AuthCookieFactory $authCookieFactory,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $this->normalizePath($request->getPathInfo());
        if ($this->isBypassedPath($path)) {
            return null;
        }

        return null !== $this->extractToken($request);
    }

    public function authenticate(Request $request): Passport
    {
        $plainToken = $this->extractToken($request);
        if (null === $plainToken) {
            throw new CustomUserMessageAuthenticationException('Aucun token de session fourni.');
        }

        $session = $this->sessionManager->findActiveSessionByPlainToken($plainToken);
        $this->assertSessionIsValid($request, $session);

        $this->sessionManager->touch($session);
        $request->attributes->set('_auth_token_refresh', [
            'plainToken' => $plainToken,
            'expiresAt' => $session->getExpiresAt(),
        ]);

        $user = $session->getUser();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn (): User => $user),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $path = $this->normalizePath($request->getPathInfo());
        if (str_starts_with($path, '/api/')) {
            $response = new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
            $response->headers->setCookie($this->authCookieFactory->clearAuthCookie($request));
            $response->headers->setCookie($this->authCookieFactory->clearDeviceCookie($request));
            $phpSessionCookie = $this->authCookieFactory->clearPhpSessionCookie($request);
            if (null !== $phpSessionCookie) {
                $response->headers->setCookie($phpSessionCookie);
            }

            return $response;
        }

        $response = new RedirectResponse($this->isProtectedPath($path) ? '/connexion' : $path);
        $response->headers->setCookie($this->authCookieFactory->clearAuthCookie($request));
        $response->headers->setCookie($this->authCookieFactory->clearDeviceCookie($request));
        $phpSessionCookie = $this->authCookieFactory->clearPhpSessionCookie($request);
        if (null !== $phpSessionCookie) {
            $response->headers->setCookie($phpSessionCookie);
        }

        return $response;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse('/connexion');
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $token = trim($matches[1]);

            return '' === $token ? null : $token;
        }

        $cookieToken = $request->cookies->get(AuthCookieFactory::AUTH_COOKIE_NAME);
        if (!is_string($cookieToken)) {
            return null;
        }

        $cookieToken = trim($cookieToken);

        return '' === $cookieToken ? null : $cookieToken;
    }

    private function assertSessionIsValid(Request $request, ?UserSession $session): void
    {
        if (!$session instanceof UserSession) {
            throw new CustomUserMessageAuthenticationException('Session introuvable, expiree ou revoquee.');
        }

        $user = $session->getUser();
        if (!$user instanceof User) {
            throw new CustomUserMessageAuthenticationException('Session invalide: utilisateur introuvable.');
        }

        if ($session->isRevoked() || null !== $session->getRevokedAt()) {
            throw new CustomUserMessageAuthenticationException('Session invalide: vous avez ete deconnecte.');
        }

        if ($session->getExpiresAt() <= new \DateTimeImmutable()) {
            throw new CustomUserMessageAuthenticationException('Session expiree. Merci de vous reconnecter.');
        }

        $currentDeviceId = $request->cookies->get(DeviceIdentifier::COOKIE_NAME);
        if (is_string($currentDeviceId) && '' !== trim($currentDeviceId) && $session->getDeviceId() !== trim($currentDeviceId)) {
            throw new CustomUserMessageAuthenticationException('Session invalide pour cet appareil.');
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim($path, '/');

        return '' === $normalized ? '/' : $normalized;
    }

    private function isBypassedPath(string $path): bool
    {
        if (in_array($path, self::BYPASS_EXACT_PATHS, true)) {
            return true;
        }

        foreach (self::BYPASS_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isProtectedPath(string $path): bool
    {
        return '/profil' === $path
            || '/commandes' === $path
            || str_starts_with($path, '/admin')
            || in_array($path, ['/api/me', '/api/logout'], true)
            || str_starts_with($path, '/api/profile')
            || (str_starts_with($path, '/api/orders') && !in_array($path, ['/api/orders/checkout', '/api/orders/guest-account'], true));
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class AuthCookieFactory
{
    public const AUTH_COOKIE_NAME = 'AUTH_TOKEN';

    public function buildAuthCookie(Request $request, string $plainToken, \DateTimeInterface $expiresAt): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue($plainToken)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires($expiresAt);
    }

    public function clearAuthCookie(Request $request): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue('')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('-1 day'));
    }

    public function clearDeviceCookie(Request $request): Cookie
    {
        return Cookie::create(DeviceIdentifier::COOKIE_NAME)
            ->withValue('')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('-1 day'));
    }

    public function clearPhpSessionCookie(Request $request): ?Cookie
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $params = session_get_cookie_params();
        $sameSite = $params['samesite'] ?? 'lax';
        if (!is_string($sameSite) || '' === $sameSite) {
            $sameSite = 'lax';
        }

        return Cookie::create($session->getName())
            ->withValue('')
            ->withHttpOnly((bool) ($params['httponly'] ?? true))
            ->withSecure((bool) ($params['secure'] ?? $request->isSecure()))
            ->withSameSite($sameSite)
            ->withPath((string) ($params['path'] ?? '/'))
            ->withExpires(new \DateTimeImmutable('-1 day'));
    }

    public function buildDeviceCookie(Request $request, string $deviceId): Cookie
    {
        return Cookie::create(DeviceIdentifier::COOKIE_NAME)
            ->withValue($deviceId)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('+5 years'));
    }
}

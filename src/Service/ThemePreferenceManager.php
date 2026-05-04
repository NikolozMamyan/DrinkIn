<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ThemePreferenceManager
{
    public const SESSION_KEY = 'drinkin_theme';
    public const COOKIE_NAME = 'drinkin_theme';

    public function resolveDarkMode(?User $user, Request $request): bool
    {
        if ($user instanceof User) {
            return $user->isDarkModeEnabled();
        }

        $theme = $request->cookies->get(self::COOKIE_NAME);
        if (!is_string($theme) || '' === $theme) {
            $theme = $request->getSession()->get(self::SESSION_KEY, 'dark');
        }

        return 'light' !== $theme;
    }

    public function persist(Request $request, Response $response, bool $darkModeEnabled): void
    {
        $theme = $darkModeEnabled ? 'dark' : 'light';
        $session = $request->getSession();

        if ($session->get(self::SESSION_KEY) !== $theme) {
            $session->set(self::SESSION_KEY, $theme);
        }

        if ($request->cookies->get(self::COOKIE_NAME) !== $theme) {
            $response->headers->setCookie(new Cookie(
                self::COOKIE_NAME,
                $theme,
                new \DateTimeImmutable('+365 days'),
                '/',
                null,
                false,
                false,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }
    }
}

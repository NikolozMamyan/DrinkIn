<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AuthCookieFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthSessionRefreshSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthCookieFactory $authCookieFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (true === $event->getRequest()->attributes->get('_skip_auth_token_refresh')) {
            return;
        }

        $refreshData = $event->getRequest()->attributes->get('_auth_token_refresh');
        if (!is_array($refreshData)) {
            return;
        }

        $plainToken = $refreshData['plainToken'] ?? null;
        $expiresAt = $refreshData['expiresAt'] ?? null;
        if (!is_string($plainToken) || !$expiresAt instanceof \DateTimeInterface) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            $this->authCookieFactory->buildAuthCookie($event->getRequest(), $plainToken, $expiresAt),
        );
    }
}

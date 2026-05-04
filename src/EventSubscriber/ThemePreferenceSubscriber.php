<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ThemePreferenceManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ThemePreferenceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ThemePreferenceManager $themePreferenceManager,
    ) {
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

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $user = $this->security->getUser();
        $darkModeEnabled = $this->themePreferenceManager->resolveDarkMode(
            $user instanceof User ? $user : null,
            $request,
        );

        $this->themePreferenceManager->persist($request, $event->getResponse(), $darkModeEnabled);
    }
}

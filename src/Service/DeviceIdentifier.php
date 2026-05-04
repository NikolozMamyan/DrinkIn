<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class DeviceIdentifier
{
    public const COOKIE_NAME = 'DEVICE_ID';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getCurrentDeviceId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $deviceId = $request->attributes->get(self::COOKIE_NAME);
        if (is_string($deviceId) && '' !== $deviceId) {
            return $deviceId;
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if (!is_string($cookie) || '' === trim($cookie)) {
            return null;
        }

        $deviceId = trim($cookie);
        $request->attributes->set(self::COOKIE_NAME, $deviceId);

        return $deviceId;
    }

    public function getOrCreateCurrentDeviceId(): string
    {
        $deviceId = $this->getCurrentDeviceId();
        if (null !== $deviceId) {
            return $deviceId;
        }

        $deviceId = bin2hex(random_bytes(32));
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $request->attributes->set(self::COOKIE_NAME, $deviceId);
        }

        return $deviceId;
    }
}

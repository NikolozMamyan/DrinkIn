<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class SessionManager
{
    private const DEFAULT_SESSION_LIFETIME = '+30 days';
    private const MOBILE_SESSION_LIFETIME = '+90 days';
    private const SHORT_SESSION_LIFETIME = '+12 hours';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSessionRepository $userSessionRepository,
        private readonly RequestStack $requestStack,
        private readonly DeviceIdentifier $deviceIdentifier,
    ) {
    }

    /**
     * @return array{0: UserSession, 1: string, 2: string}
     */
    public function createSession(User $user, ?string $deviceName = null, bool $persistent = true): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $plainToken = bin2hex(random_bytes(32));
        $session = (new UserSession())
            ->setUser($user)
            ->setTokenHash(hash('sha256', $plainToken))
            ->setDeviceId($this->deviceIdentifier->getOrCreateCurrentDeviceId())
            ->setDeviceName($deviceName)
            ->setUserAgent($request?->headers->get('User-Agent'))
            ->setIpAddress($request?->getClientIp())
            ->setLastActivityAt(new \DateTimeImmutable());

        $session->setExpiresAt($this->buildSessionExpiryDate($session->getUserAgent(), $persistent));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return [$session, $plainToken, $session->getDeviceId()];
    }

    public function findActiveSessionByPlainToken(string $plainToken): ?UserSession
    {
        $session = $this->userSessionRepository->findOneByTokenHash(hash('sha256', $plainToken));
        if (!$session instanceof UserSession) {
            return null;
        }

        if ($session->isRevoked() || $session->getExpiresAt() <= new \DateTimeImmutable()) {
            return null;
        }

        return $session;
    }

    public function touch(UserSession $session): void
    {
        $session
            ->setLastActivityAt(new \DateTimeImmutable())
            ->setExpiresAt($this->buildSessionExpiryDate($session->getUserAgent()));

        $this->entityManager->flush();
    }

    public function revoke(UserSession $session, ?string $reason = null): void
    {
        $session
            ->setIsRevoked(true)
            ->setRevokedAt(new \DateTimeImmutable())
            ->setRevokedReason($reason);

        $this->entityManager->flush();
    }

    public function revokeAllForUser(User $user, ?int $exceptSessionId = null): int
    {
        $sessions = $this->userSessionRepository->findActiveByUser($user);
        $count = 0;

        foreach ($sessions as $session) {
            if (null !== $exceptSessionId && $session->getId() === $exceptSessionId) {
                continue;
            }

            $session
                ->setIsRevoked(true)
                ->setRevokedAt(new \DateTimeImmutable())
                ->setRevokedReason('logout_all');
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    public function getCurrentDeviceId(): ?string
    {
        return $this->deviceIdentifier->getCurrentDeviceId();
    }

    private function buildSessionExpiryDate(?string $userAgent, bool $persistent = true): \DateTimeImmutable
    {
        if (!$persistent) {
            return new \DateTimeImmutable(self::SHORT_SESSION_LIFETIME);
        }

        return new \DateTimeImmutable($this->isMobileUserAgent($userAgent)
            ? self::MOBILE_SESSION_LIFETIME
            : self::DEFAULT_SESSION_LIFETIME);
    }

    private function isMobileUserAgent(?string $userAgent): bool
    {
        if (!is_string($userAgent) || '' === trim($userAgent)) {
            return false;
        }

        return 1 === preg_match('/Android|iPhone|iPad|iPod/i', $userAgent);
    }
}

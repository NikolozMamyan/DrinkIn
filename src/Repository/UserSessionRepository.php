<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
final class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?UserSession
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return list<UserSession>
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('session')
            ->andWhere('session.user = :user')
            ->andWhere('session.isRevoked = false')
            ->andWhere('session.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('session.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

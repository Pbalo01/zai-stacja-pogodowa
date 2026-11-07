<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ApiTokenManager
{
    public const TTL_SECONDS = 3600;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function issue(User $user): string
    {
        // Przy okazji sprzątamy wygasłe tokeny tego użytkownika
        $this->em->createQuery('DELETE FROM App\Entity\ApiToken t WHERE t.user = :user AND t.expiresAt <= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->execute();

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', self::TTL_SECONDS));
        $this->em->persist(new ApiToken($user, self::hash($plainToken), $expiresAt));
        $this->em->flush();

        return $plainToken;
    }

    public function revoke(string $plainToken): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\ApiToken t WHERE t.tokenHash = :hash')
            ->setParameter('hash', self::hash($plainToken))
            ->execute();
    }

    public static function extractBearer(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization');

        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
    }
}
